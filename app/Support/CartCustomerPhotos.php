<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CartCustomerPhotos
{
    public const MAX_PHOTOS = 8;
    private const KEY = 'cart_customer_photos';

    /** @return array<int, array{path:string,name:string}> */
    public static function forKey(Request $request, string $key): array
    {
        $all = $request->session()->get(self::KEY, []);
        if (!is_array($all)) return [];

        $photos = $all[$key] ?? [];
        if (!is_array($photos)) return [];

        return array_values(array_filter($photos, static fn ($photo) => is_array($photo)
            && is_string($photo['path'] ?? null)
            && str_starts_with($photo['path'], 'customer-photos/drafts/')
            && Storage::disk('local')->exists($photo['path'])));
    }

    public static function hasValid(Request $request, string $key): bool
    {
        return count(self::forKey($request, $key)) > 0;
    }

    /**
     * The only way to create session references to private uploads.
     * No path supplied by the browser is ever trusted.
     *
     * @param array<string, UploadedFile[]> $incoming keyed by product:option
     */
    public static function addMany(Request $request, array $incoming): void
    {
        if (!$incoming) return;

        $stored = $request->session()->get(self::KEY, []);
        $stored = is_array($stored) ? $stored : [];
        $newPaths = [];
        try {
            foreach ($incoming as $key => $files) {
                $before = self::forKey($request, $key);
                if (count($before) + count($files) > self::MAX_PHOTOS) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'customer_photos' => 'Her ürün/seçenek için en fazla '.self::MAX_PHOTOS.' fotoğraf eklenebilir.',
                    ]);
                }
                $list = $before;
                foreach ($files as $file) {
                    $path = $file->store('customer-photos/drafts/'.Str::uuid(), 'local');
                    if (!$path) throw new \RuntimeException('Fotoğraf saklanamadı. Lütfen tekrar deneyin.');
                    $newPaths[] = $path;
                    $list[] = [
                        'path' => $path,
                        'name' => mb_substr(basename($file->getClientOriginalName()), 0, 120),
                    ];
                }
                $stored[$key] = $list;
            }
            $request->session()->put(self::KEY, $stored);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($newPaths);
            throw $e;
        }
    }

    /** Remove draft-only photos when a cart line is removed; ordered copies stay intact. */
    public static function forget(Request $request, string $key): void
    {
        $stored = $request->session()->get(self::KEY, []);
        if (!is_array($stored) || !isset($stored[$key])) return;
        foreach (self::forKey($request, $key) as $photo) {
            Storage::disk('local')->delete($photo['path']);
        }
        unset($stored[$key]);
        $request->session()->put(self::KEY, $stored);
    }

    /** Keep photo drafts only for cart lines still present. */
    public static function prune(Request $request, array $activeKeys): void
    {
        $stored = $request->session()->get(self::KEY, []);
        if (!is_array($stored)) return;
        foreach (array_keys($stored) as $key) {
            if (!in_array((string) $key, array_map('strval', $activeKeys), true)) {
                self::forget($request, (string) $key);
            }
        }
    }

    /** Copies are tied to immutable order item records and cannot be changed by cart removal. */
    public static function copyToOrder(Request $request, string $key, int $orderId, int $orderItemId): array
    {
        $result = [];
        $copied = [];
        try {
            foreach (self::forKey($request, $key) as $index => $photo) {
                $extension = strtolower(pathinfo($photo['path'], PATHINFO_EXTENSION));
                if (!in_array($extension, ['jpg','jpeg','png','webp','avif'], true)) {
                    throw new \RuntimeException('Desteklenmeyen müşteri fotoğrafı biçimi.');
                }
                $to = 'customer-photos/orders/'.$orderId.'/'.$orderItemId.'/'.Str::uuid().'.'.$extension;
                if (!Storage::disk('local')->copy($photo['path'], $to)) {
                    throw new \RuntimeException('Sipariş fotoğrafları kaydedilemedi.');
                }
                $copied[] = $to;
                $result[] = ['path' => $to, 'name' => $photo['name']];
            }
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($copied);
            throw $e;
        }
        return $result;
    }

    public static function clear(Request $request): void
    {
        $stored = $request->session()->get(self::KEY, []);
        if (is_array($stored)) {
            foreach (array_keys($stored) as $key) self::forget($request, (string) $key);
        }
        $request->session()->forget(self::KEY);
    }
}
