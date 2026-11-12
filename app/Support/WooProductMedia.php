<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class WooProductMedia
{
    /**
     * Download only images from our existing WordPress host.
     * Never follow redirects, proxy untrusted hosts, or store executable files.
     * A failed image cannot cancel a successfully imported product.
     *
     * @return list<string> Human-readable image warnings.
     */
    public function sync(Product $product, string $imagesText): array
    {
        if ($imagesText === '') {
            return [];
        }

        $urls = preg_split('/,\s*(?=https?:\/\/)/i', $imagesText) ?: [];
        $errors = [];

        foreach (array_slice($urls, 0, 20) as $index => $input) {
            $url = trim($input);
            if ($url === '') {
                continue;
            }
            try {
                $this->syncOne($product, $url, $index);
            } catch (\Throwable $error) {
                $errors[] = 'Görsel '.($index + 1).': '.$error->getMessage();
            }
        }
        if (count($urls) > 20) {
            $errors[] = '20 görsel sınırını aşan medya dosyaları alınmadı.';
        }

        return $errors;
    }

    private function syncOne(Product $product, string $url, int $sort): void
    {
        $parts = parse_url($url);
        $host = mb_strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https'
            || !in_array($host, ['polariskursun.com', 'www.polariskursun.com'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || !str_starts_with($parts['path'] ?? '', '/wp-content/uploads/')) {
            throw new RuntimeException('Yalnızca eski WordPress mağazasının HTTPS medya adresleri destekleniyor.');
        }

        $id = hash('sha256', $url);
        $prefix = 'imports/products/'.$product->id.'/';
        $disk = Storage::disk('public');
        $existing = $product->images()->where('path', 'like', 'storage/'.$prefix.$id.'.%')->first();
        if ($existing && $disk->exists(substr($existing->path, 8))) {
            return;
        }
        if ($product->images()->count() >= 20) {
            throw new RuntimeException('Ürünün 20 görsel sınırı dolu.');
        }

        // Do not permit redirects from WordPress to arbitrary internal URLs.
        $response = Http::withOptions([
            'allow_redirects' => false,
            'verify' => true,
            'connect_timeout' => 4,
        ])->timeout(9)->get($url);

        if (!$response->successful()) {
            throw new RuntimeException('Kaynak görsele erişilemedi (HTTP '.$response->status().').');
        }

        $body = $response->body();
        if (strlen($body) > 5 * 1024 * 1024) {
            throw new RuntimeException('Görsel 5 MB sınırını aşıyor.');
        }

        $info = @getimagesizefromstring($body);
        $mime = is_array($info) ? ($info['mime'] ?? '') : '';
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => null,
        };
        if (!$ext || ($info[0] ?? 0) > 6000 || ($info[1] ?? 0) > 6000) {
            throw new RuntimeException('Görsel biçimi veya boyutu uygun değil.');
        }

        $path = $prefix.$id.'.'.$ext;
        if (!$disk->put($path, $body)) {
            throw new RuntimeException('Görsel diske yazılamadı.');
        }
        $product->images()->firstOrCreate(
            ['path' => 'storage/'.$path],
            ['sort_order' => $sort]
        );
        if (!$product->image_path || $sort === 0 && $product->images()->count() === 1) {
            $product->update(['image_path' => 'storage/'.$path]);
        }
    }
}
