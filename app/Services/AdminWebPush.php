<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Support\AdminOrderNotifications;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AdminWebPush
{
    public static function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    // Only documented browser push providers are permitted. No arbitrary
    // server URLs, credentials, alternate ports or HTTP redirects (SSRF).
    public static function audience(string $endpoint): ?string
    {
        if (strlen($endpoint) > 2048 || preg_match('/[\\x00-\\x20\\x7f\\\\\\\\]/', $endpoint)) return null;
        $p = parse_url($endpoint);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || !isset($p['host'],$p['path'])
            || isset($p['user'],$p['pass']) || isset($p['user']) || isset($p['pass']) || isset($p['port']) || isset($p['fragment'])) return null;
        $host = strtolower($p['host']);
        $valid = in_array($host, ['fcm.googleapis.com','updates.push.services.mozilla.com','web.push.apple.com'], true)
            || str_ends_with($host, '.push.apple.com') || str_ends_with($host, '.notify.windows.com');
        if (!$valid || $p['path'] === '/') return null;
        return 'https://'.$host;
    }

    public function keys(): object
    {
        $existing = DB::table('admin_push_keys')->where('id',1)->first();
        if ($existing) return $existing;
        $key = openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);
        if (!$key || !openssl_pkey_export($key,$pem)) throw new RuntimeException('Web Push keys unavailable');
        $details = openssl_pkey_get_details($key);
        $x = $details['ec']['x'] ?? ''; $y = $details['ec']['y'] ?? '';
        if ($x === '' || $y === '' || strlen($x)>32 || strlen($y)>32) throw new RuntimeException('Invalid EC key');
        $pub = self::b64("\x04".str_pad($x,32,"\0",STR_PAD_LEFT).str_pad($y,32,"\0",STR_PAD_LEFT));
        DB::table('admin_push_keys')->insertOrIgnore(['id'=>1,'private_key'=>Crypt::encryptString($pem),'public_key'=>$pub]);
        return DB::table('admin_push_keys')->where('id',1)->first();
    }

    public static function sessionHash(Request $request): string
    {
        return hash('sha256',$request->session()->getId());
    }

    public static function revokeSession(Request $request): void
    {
        if ($request->user()) DB::table('admin_push_subscriptions')->where('user_id',$request->user()->id)
            ->where('session_hash',self::sessionHash($request))->delete();
    }

    public function send(string $endpoint): int
    {
        $audience = self::audience($endpoint);
        if (!$audience) return 410;
        $keys = $this->keys();
        $subject = rtrim((string)config('app.url'),'/');
        if (!str_starts_with($subject,'https://')) $subject = 'https://newtic.invalid';
        $token = JWT::encode(['aud'=>$audience,'exp'=>time()+3600,'sub'=>$subject],
            Crypt::decryptString($keys->private_key),'ES256');
        // RFC 8030/8292: empty-body push deliberately sends no order data.
        return Http::withOptions(['allow_redirects'=>false])->connectTimeout(3)->timeout(6)
            ->withHeaders(['Authorization'=>'vapid t='.$token.', k='.$keys->public_key,
                'TTL'=>'300','Urgency'=>'high','Topic'=>'newtic-orders'])
            ->withBody('','application/octet-stream')->post($endpoint)->status();
    }

    public function deliverPending(int $limit=20): int
    {
        DB::table('admin_push_subscriptions')->where('expires_at','<=',now())->delete();
        // Round-robin by updated time prevents one failing endpoint starving others.
        $rows = DB::table('admin_push_subscriptions')->where(fn($q)=>$q->whereNull('retry_at')->orWhere('retry_at','<=',now()))
            ->whereExists(fn($q)=>$q->selectRaw('1')->from('orders')->whereColumn('orders.id','>','admin_push_subscriptions.last_order_id')
                ->whereIn('orders.status',['pending_payment','preparing']))
            ->orderBy('updated_at')->limit($limit)->get();
        $sent = 0;
        $deadline = microtime(true)+45;
        foreach ($rows as $row) {
            if (microtime(true)>$deadline) break;
            $user = User::find($row->user_id);
            $query = DB::table('admin_push_subscriptions')->where('id',$row->id)->where('session_hash',$row->session_hash);
            if (!$user?->is_admin) { $query->delete(); continue; }
            if (!$query->exists()) continue;
            $newest = (int)AdminOrderNotifications::unread($user)->where('orders.id','>',$row->last_order_id)->max('orders.id');
            if (!$newest) {
                // Already-read orders should not trigger a delayed alert.
                $current = (int)Order::query()->whereIn('status',['pending_payment','preparing'])->max('id');
                $query->update(['last_order_id'=>max($row->last_order_id,$current),'updated_at'=>now()]);
                continue;
            }
            try { $status = $this->send(Crypt::decryptString($row->endpoint)); }
            catch (\Throwable) { $status=503; } // Never log private endpoints.
            if ($status>=200 && $status<300) {
                $query->update(['last_order_id'=>$newest,'retry_at'=>null,'updated_at'=>now()]);
                $sent++;
            } elseif (in_array($status,[404,410],true)) {
                $query->delete();
            } else {
                $query->update(['retry_at'=>now()->addMinutes(5),'updated_at'=>now()]);
            }
        }
        return $sent;
    }
}
