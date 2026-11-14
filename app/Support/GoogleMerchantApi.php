<?php

namespace App\Support;

use App\Models\GoogleMerchantSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoogleMerchantApi
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const OAUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const API_URL = 'https://merchantapi.googleapis.com';

    public static function authorizeUrl(GoogleMerchantSetting $settings, string $state): string
    {
        return self::OAUTH_URL.'?'.http_build_query([
            'client_id' => $settings->client_id,
            'redirect_uri' => route('admin.google-merchant.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/content',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(GoogleMerchantSetting $settings, string $code): string
    {
        $response = Http::asForm()->timeout(12)->withOptions(['allow_redirects' => false])
            ->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'redirect_uri' => route('admin.google-merchant.callback'),
                'grant_type' => 'authorization_code',
            ]);
        if (!$response->successful() || !filled($response->json('refresh_token'))) {
            throw new RuntimeException('Google bağlantısı tamamlanamadı. İzinleri kontrol edip tekrar bağlanın (HTTP '.$response->status().').');
        }
        $scope = (string) $response->json('scope', '');
        if ($scope !== '' && ! str_contains($scope, 'https://www.googleapis.com/auth/content')) {
            throw new RuntimeException('Google Merchant Center ürün yönetimi izni verilmedi.');
        }
        return (string) $response->json('refresh_token');
    }

    public function accessToken(GoogleMerchantSetting $settings): string
    {
        if (!$settings->isConnected()) {
            throw new RuntimeException('Önce Google hesabını bağlayın.');
        }
        $response = Http::asForm()->timeout(12)->withOptions(['allow_redirects' => false])
            ->post(self::TOKEN_URL, [
                'client_id' => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'refresh_token' => $settings->refresh_token,
                'grant_type' => 'refresh_token',
            ]);
        if (!$response->successful() || !filled($response->json('access_token'))) {
            throw new RuntimeException('Google erişim izni yenilenemedi (HTTP '.$response->status().'). Yeniden Google hesabına bağlanın.');
        }
        return (string) $response->json('access_token');
    }

    /** @return list<array{name:string,label:string}> */
    public function accounts(string $accessToken): array
    {
        $all = [];
        $page = null;
        for ($p = 0; $p < 10; $p++) {
            $query = ['pageSize' => 250];
            if ($page) $query['pageToken'] = $page;
            $response = $this->request('GET', '/accounts/v1/accounts', $accessToken, $query);
            foreach ($response['accounts'] ?? [] as $account) {
                if (!preg_match('/^accounts\/\d+$/D', (string) ($account['name'] ?? ''))) {
                    continue;
                }
                $all[] = [
                    'name' => $account['name'],
                    'label' => (string) ($account['accountName'] ?? $account['name']),
                ];
            }
            $page = $response['nextPageToken'] ?? null;
            if (!$page) break;
        }
        return $all;
    }

    public function createDataSource(string $accessToken, string $merchantId): string
    {
        $this->assertMerchant($merchantId);
        $response = $this->request('POST', '/datasources/v1/accounts/'.$merchantId.'/dataSources', $accessToken, [], [
            'displayName' => 'NEWTIC API - Türkiye',
            'primaryProductDataSource' => [
                'contentLanguage' => 'tr',
                'feedLabel' => 'TR',
                'countries' => ['TR'],
            ],
        ]);
        $name = (string) ($response['name'] ?? '');
        if (!preg_match('~^accounts/'.preg_quote($merchantId, '~').'/dataSources/\d+$~D', $name)) {
            throw new RuntimeException('Google geçerli bir veri kaynağı döndürmedi.');
        }
        return $name;
    }

    public function insert(string $token, GoogleMerchantSetting $setting, array $product): void
    {
        $this->assertReady($setting);
        $this->request('POST',
            '/products/v1/accounts/'.$setting->merchant_id.'/productInputs:insert',
            $token, ['dataSource' => $setting->data_source_name], $product
        );
    }

    public function delete(string $token, GoogleMerchantSetting $setting, string $offerId): void
    {
        $this->assertReady($setting);
        // RFC 4648 base64url encoding is safe even for future special characters in IDs.
        $identifier = rtrim(strtr(base64_encode('tr~TR~'.$offerId), '+/', '-_'), '=');
        $this->request('DELETE',
            '/products/v1/accounts/'.$setting->merchant_id.'/productInputs/'.$identifier,
            $token, ['dataSource' => $setting->data_source_name]
        );
    }

    private function assertMerchant(string $id): void
    {
        if (!preg_match('/^\d{4,20}$/D', $id)) {
            throw new RuntimeException('Geçersiz Merchant Center hesap numarası.');
        }
    }

    private function assertReady(GoogleMerchantSetting $setting): void
    {
        if (!$setting->isReady()) throw new RuntimeException('Google entegrasyonu henüz hazır değil.');
        $this->assertMerchant($setting->merchant_id);
        if (!preg_match('~^accounts/'.preg_quote($setting->merchant_id, '~').'/dataSources/\d+$~D', $setting->data_source_name)) {
            throw new RuntimeException('Geçersiz Google ürün veri kaynağı.');
        }
    }

    private function request(string $method, string $path, string $token, array $query = [], ?array $body = null): array
    {
        $url = self::API_URL.$path;
        $http = Http::withToken($token)->acceptJson()->timeout(7)
            ->withOptions(['allow_redirects' => false]);
        $response = match ($method) {
            'POST' => $http->post($url.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : ''), $body ?? []),
            'DELETE' => $http->delete($url.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '')),
            default => $http->get($url, $query),
        };
        if (!$response->successful()) {
            // Never echo raw responses or OAuth credentials into the UI/logs.
            throw new RuntimeException('Google Merchant API hata kodu: HTTP '.$response->status().
                '. Hesap yetkisi, geliştirici kaydı, ürün verileri veya kota ayarlarını kontrol edin.');
        }
        return $response->json() ?: [];
    }
}