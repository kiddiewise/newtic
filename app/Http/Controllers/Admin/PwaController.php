<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AdminWebPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class PwaController extends Controller
{
    public function index()
    {
        return view('admin.pwa.index');
    }

    private function current(Request $request)
    {
        return DB::table('admin_push_subscriptions')->where('user_id',$request->user()->id)
            ->where('session_hash',AdminWebPush::sessionHash($request))->where('expires_at','>',now());
    }

    public function status(Request $request, AdminWebPush $push)
    {
        try { $key = $push->keys()->public_key; }
        catch (\Throwable) { return response()->json(['message'=>'Bildirim anahtarı oluşturulamadı.'],503); }
        $active = $this->current($request)->exists();
        return response()->json(['public_key'=>$key,'active'=>$active]);
    }

    public function subscribe(Request $request, AdminWebPush $push)
    {
        $data = $request->validate(['endpoint'=>['required','string','max:2048']]);
        abort_unless(AdminWebPush::audience($data['endpoint']),422,'Desteklenmeyen bildirim sağlayıcısı.');
        $push->keys();
        $hash = hash('sha256',$data['endpoint']);
        $session = AdminWebPush::sessionHash($request);
        $user = $request->user();
        DB::transaction(function () use ($hash,$session,$user,$data) {
            // A browser endpoint belongs to just one signed-in account/session.
            $old = DB::table('admin_push_subscriptions')->where('endpoint_hash',$hash)->first();
            $same = $old && (int)$old->user_id === (int)$user->id && $old->session_hash === $session;
            DB::table('admin_push_subscriptions')->upsert([[
                'endpoint_hash'=>$hash,'user_id'=>$user->id,'session_hash'=>$session,
                'endpoint'=>Crypt::encryptString($data['endpoint']),
                'last_order_id'=>$same ? $old->last_order_id : (int)Order::query()->max('id'),
                'expires_at'=>now()->addDays(30),'retry_at'=>null,'created_at'=>now(),'updated_at'=>now(),
            ]],['endpoint_hash'],['user_id','session_hash','endpoint','last_order_id','expires_at','retry_at','updated_at']);
        });
        return response()->json(['active'=>true]);
    }

    public function unsubscribe(Request $request)
    {
        $this->current($request)->delete();
        return response()->json(['active'=>false]);
    }

    public function test(Request $request, AdminWebPush $push)
    {
        $row = $this->current($request)->latest('id')->first();
        abort_unless($row,422,'Önce bildirimleri etkinleştirin.');
        try { $status=$push->send(Crypt::decryptString($row->endpoint)); }
        catch (\Throwable) { $status=503; }
        if (in_array($status,[404,410],true)) $this->current($request)->where('id',$row->id)->delete();
        return response()->json(['accepted'=>$status>=200 && $status<300],$status>=200 && $status<300 ? 200 : 502);
    }
}
