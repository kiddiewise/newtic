<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class UrlRedirect extends Model
{
    protected $fillable = ['old_path', 'new_path', 'is_active', 'hit_count', 'last_hit_at'];

    protected $casts = [
        'is_active' => 'boolean', 'hit_count' => 'integer', 'last_hit_at' => 'datetime',
    ];

    /**
     * Domain-independent URL path. Accept full old/new URLs only to extract
     * their paths. Redirects always target the NEWTIC site's current host.
     */
    public static function normalize(string $input): string
    {
        $input = trim($input);
        if ($input === '' || strlen($input) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $input)
            || str_contains($input, '\\')) {
            throw new InvalidArgumentException('Geçersiz URL veya yol.');
        }

        if (preg_match('~^https?://~i', $input)) {
            $parsed = parse_url($input);
            if (!is_array($parsed) || empty($parsed['host'])
                || isset($parsed['user']) || isset($parsed['pass'])) {
                throw new InvalidArgumentException('Geçerli bir HTTPS/HTTP URL girin.');
            }
            $input = $parsed['path'] ?? '/';
        } elseif (!str_starts_with($input, '/') || str_starts_with($input, '//')) {
            throw new InvalidArgumentException('URL / ile başlamalı veya tam bir http(s) adresi olmalıdır.');
        }

        $path = parse_url($input, PHP_URL_PATH);
        if (!is_string($path)) throw new InvalidArgumentException('URL yolu okunamadı.');
        $path = rawurldecode($path);
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')
            || preg_match('/[\x00-\x1F\x7F?#\\\\]/u', $path)
            || !mb_check_encoding($path, 'UTF-8')) {
            throw new InvalidArgumentException('Geçersiz URL yolu.');
        }
        $segments = explode('/', $path);
        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            throw new InvalidArgumentException('URL yolu .. veya . segmenti içeremez.');
        }
        $path = preg_replace('~/+~', '/', $path);
        if ($path === null) throw new InvalidArgumentException('URL yolu geçersiz.');
        $path = rtrim($path, '/') ?: '/';
        if (mb_strlen($path) > 512) {
            throw new InvalidArgumentException('URL yolu en fazla 512 karakter olmalıdır.');
        }
        return $path;
    }

    public static function validatePair(string $old, string $new): void
    {
        if ($old === '/' || $old === $new) {
            throw new InvalidArgumentException('Ana sayfa veya kendi kendine yönlendirme tanımlanamaz.');
        }
        // Reserved paths should never be redirected by custom merchant rules.
        foreach ([$old, $new] as $path) {
            if (preg_match('~^/(admin|odeme|siparis|hesabim|giris|uye-ol|sepet|kargo|google|sitemap|robots\.txt)(/|$|\.)~i', $path)) {
                throw new InvalidArgumentException('Yönetim, ödeme veya sistem URL yolları yönlendirilemez.');
            }
        }
        if (str_contains($old, '/./') || str_contains($new, '/../')) {
            throw new InvalidArgumentException('Geçersiz yol.');
        }
    }

    /** Reject redirect loops and chains longer than 12. */
    public static function assertAcyclic(array $pairs): void
    {
        foreach ($pairs as $start => $next) {
            $seen = [$start => true];
            $current = $next;
            for ($depth = 0; $depth < 12 && isset($pairs[$current]); $depth++) {
                if (isset($seen[$current])) {
                    throw new InvalidArgumentException('Yönlendirme döngüsü bulundu: '.$start);
                }
                $seen[$current] = true;
                $current = $pairs[$current];
            }
            if (isset($pairs[$current])) {
                throw new InvalidArgumentException('Yönlendirme zinciri 12 adımdan uzun olamaz.');
            }
        }
    }
}