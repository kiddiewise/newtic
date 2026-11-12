<?php

namespace App\Services;

use App\Models\ShippingProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Kargo Entegratör API v1.
 * Fixed origin and fixed endpoint allowlist; customer input never enters URL paths.
 */
final class KargoIntegratorClient
{
    private const BASE = 'https://app.kargoentegrator.com/api';

    private function request(ShippingProvider $provider): \Illuminate\Http\Client\PendingRequest
    {
        if ($provider->provider !== 'kargoentegrator' || ! $provider->hasKey()) {
            throw new RuntimeException('Kargo Entegratör API anahtarı bulunamadı.');
        }

        return Http::withToken($provider->api_key)
            ->acceptJson()
            ->withOptions(['allow_redirects' => false, 'verify' => true, 'connect_timeout' => 5])
            ->timeout(15);
    }

    public function carriers(ShippingProvider $provider): array
    {
        $json = $this->json($this->request($provider)->get(self::BASE.'/integration/cargos'));
        return $this->rows($json);
    }

    public function warehouses(ShippingProvider $provider): array
    {
        $json = $this->json($this->request($provider)->get(self::BASE.'/settings/warehouses'));
        return $this->rows($json);
    }

    public function shipment(ShippingProvider $provider, int $remoteId): array
    {
        $json = $this->json($this->request($provider)->get(self::BASE.'/shipments/'.$this->id($remoteId)));
        $item = $json['data'] ?? $json;
        if (!is_array($item) || !isset($item['id']) || (int)$item['id'] !== $remoteId) {
            throw new RuntimeException('Gönderi yanıtındaki ID doğrulanamadı.');
        }
        return $item;
    }

    public function create(ShippingProvider $provider, array $payload): array
    {
        $json = $this->json($this->request($provider)->post(self::BASE.'/shipments', $payload));
        $item = $json['data'] ?? $json;
        if (!is_array($item) || !is_numeric($item['id'] ?? null)
            || (int)$item['id'] < 1) {
            throw new RuntimeException('Gönderi API yanıtı doğrulanamadı; panelden kontrol edin. Yeniden gönderi oluşturmayın.');
        }
        return $item;
    }

    public function label(ShippingProvider $provider, int $remoteId): string
    {
        $response = $this->request($provider)->get(
            self::BASE.'/print-pdf?shipments%5B0%5D='.$this->id($remoteId)
        );
        if (!$response->successful()) {
            throw new RuntimeException('Kargo etiketi şu anda alınamadı.');
        }
        $content = $response->body();
        if (strlen($content) < 5 || strlen($content) > 5 * 1024 * 1024
            || !str_starts_with($content, '%PDF-')) {
            throw new RuntimeException('Kargo sağlayıcısı geçerli bir PDF göndermedi.');
        }
        return $content;
    }

    public static function carrierCode(array $item): string
    {
        $cargo = $item['cargo_company'] ?? $item['cargo'] ?? [];
        $code = is_array($cargo) ? ($cargo['id'] ?? $cargo['code'] ?? null) : null;
        $code ??= $item['cargo_company_id'] ?? $item['cargo_id'] ?? null;
        return is_string($code) ? mb_strtolower(trim($code)) : '';
    }

    public static function accountId(array $item): ?int
    {
        $id = $item['id'] ?? null;
        return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    }

    public static function accountTitle(array $item, string $fallback): string
    {
        $cargo = $item['cargo_company'] ?? [];
        $name = $item['name'] ?? $item['title'] ?? (is_array($cargo) ? ($cargo['title'] ?? null) : null);
        return mb_substr(trim((string)($name ?: $fallback)), 0, 190);
    }

    private function rows(array $data): array
    {
        $list = $data['data'] ?? $data;
        if (is_array($list) && isset($list['data']) && is_array($list['data'])) {
            $list = $list['data'];
        }
        if (!is_array($list) || !array_is_list($list)) {
            throw new RuntimeException('Sağlayıcının liste yanıtı beklenen biçimde değil.');
        }
        return array_values(array_filter($list, 'is_array'));
    }

    private function json(Response $response): array
    {
        if (!$response->successful()) {
            // Never expose API-provided error text: it can contain credentials or PII.
            throw new RuntimeException('Kargo Entegratör bağlantısı başarısız (HTTP '.$response->status().').');
        }
        $json = $response->json();
        if (!is_array($json)) {
            throw new RuntimeException('Kargo Entegratör geçerli JSON yanıtı göndermedi.');
        }
        return $json;
    }

    private function id(int $id): int
    {
        if ($id < 1) throw new \InvalidArgumentException('Geçersiz kargo gönderi kimliği.');
        return $id;
    }
}
