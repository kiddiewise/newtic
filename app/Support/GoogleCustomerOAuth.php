<?php

namespace App\Support;

use App\Models\GoogleLoginSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class GoogleCustomerOAuth
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const VERIFY_ENDPOINT = 'https://oauth2.googleapis.com/tokeninfo';

    public function authorizationUrl(GoogleLoginSetting $settings, string $state, string $nonce, string $verifier): string
    {
        return self::AUTH_ENDPOINT.'?'.http_build_query([
            'client_id' => $settings->client_id,
            'redirect_uri' => route('account.google.callback'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'access_type' => 'online',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Google verifies the ID token signature at its tokeninfo endpoint.
     * The code is exchanged server-to-server using a confidential client,
     * and every identity claim is checked again before opening a session.
     *
     * @return array{sub:string,email:string,name:string}
     */
    public function exchangeAndVerify(GoogleLoginSetting $settings, string $code, string $verifier, string $nonce): array
    {
        $response = Http::asForm()->timeout(12)->withOptions(['allow_redirects' => false])
            ->post(self::TOKEN_ENDPOINT, [
                'client_id' => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'code' => $code,
                'redirect_uri' => route('account.google.callback'),
                'code_verifier' => $verifier,
                'grant_type' => 'authorization_code',
            ]);
        if (!$response->successful() || !filled($response->json('id_token'))) {
            throw new RuntimeException('Google kimlik doğrulaması tamamlanamadı. Yeniden deneyin.');
        }

        $verification = Http::acceptJson()->timeout(12)->withOptions(['allow_redirects' => false])
            ->get(self::VERIFY_ENDPOINT, ['id_token' => (string) $response->json('id_token')]);
        if (!$verification->successful()) {
            throw new RuntimeException('Google kimlik doğrulaması geçersiz. Yeniden deneyin.');
        }
        $claims = (array) $verification->json();
        $verified = $claims['email_verified'] ?? false;
        $verified = $verified === true || $verified === 'true' || $verified === '1' || $verified === 1;
        $issuer = (string) ($claims['iss'] ?? '');
        $expiry = (int) ($claims['exp'] ?? 0);
        $subject = (string) ($claims['sub'] ?? '');
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $name = trim((string) ($claims['name'] ?? ''));

        if (!hash_equals((string) $settings->client_id, (string) ($claims['aud'] ?? ''))
            || !in_array($issuer, ['https://accounts.google.com', 'accounts.google.com'], true)
            || $expiry <= now()->timestamp
            || !$verified
            || !hash_equals($nonce, (string) ($claims['nonce'] ?? ''))
            || !preg_match('/^[0-9]{5,255}$/D', $subject)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || mb_strlen($email) > 255) {
            throw new RuntimeException('Google hesabı doğrulanamadı. Farklı bir hesapla yeniden deneyin.');
        }

        return [
            'sub' => $subject,
            'email' => $email,
            'name' => Str::limit($name !== '' ? $name : strstr($email, '@', true), 160, ''),
        ];
    }
}