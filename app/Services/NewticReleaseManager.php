<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Immutable release channel. A shop only trusts a full production ZIP published
 * by the canonical NEWTIC repository with a SHA256 digest from GitHub Releases.
 * No merchant-supplied URL, branch name or arbitrary script is ever executed.
 */
final class NewticReleaseManager
{
    private const API = 'https://api.github.com/repos/kiddiewise/newtic/releases/latest';
    private const ZIP_NAME = 'newtic-customer-install.zip';

    public function path(string $name): string
    {
        return storage_path('app/private/newtic-updates/'.$name);
    }

    public function currentVersion(): string
    {
        foreach ([base_path('.newtic-installed-version'), base_path('VERSION')] as $path) {
            if (!is_file($path)) continue;
            $version = trim((string) file_get_contents($path));
            if (preg_match('/^\d+\.\d+\.\d+$/D', $version)) return $version;
        }

        return '0.0.0';
    }

    public function state(): array
    {
        $path = $this->path('state.json');
        if (!is_file($path)) return ['status' => 'idle'];

        $value = json_decode((string) file_get_contents($path), true);
        return is_array($value) ? $value : ['status' => 'idle'];
    }

    public function writeState(array $value): void
    {
        $this->writeJson('state.json', $value);
    }

    public function latest(): ?array
    {
        $path = $this->path('latest.json');
        if (!is_file($path)) return null;

        $value = json_decode((string) file_get_contents($path), true);
        if (!is_array($value) || !isset($value['version'])) return null;
        return $value;
    }

    public function hasUpdate(): bool
    {
        $release = $this->latest();
        return $release !== null && version_compare($release['version'], $this->currentVersion(), '>');
    }

    public function refresh(): ?array
    {
        try {
            $previewTag = (string) config('newtic.update_preview_tag', '');
            $url = self::API;
            if ($previewTag !== '') {
                if (!preg_match('/^v\d+\.\d+\.\d+$/D', $previewTag)) {
                    throw new RuntimeException('invalid_preview_tag');
                }
                $url = 'https://api.github.com/repos/kiddiewise/newtic/releases/tags/'.$previewTag;
            }
            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'NEWTIC-Merchant-Updater',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])->connectTimeout(5)->timeout(12)->get($url);

            if (!$response->successful()) throw new RuntimeException('release_api_unavailable');
            $release = $this->parse($response->json());
            $release['checked_at'] = now()->toIso8601String();
            $this->writeJson('latest.json', $release);

            return $release;
        } catch (Throwable $e) {
            // Do not replace last known-good release metadata on network failure.
            Log::warning('newtic_release_lookup_failed', ['error_type' => $e::class]);
            return null;
        }
    }

    public function parse(mixed $payload): array
    {
        $previewTag = (string) config('newtic.update_preview_tag', '');
        $previewMatches = $previewTag !== '' && ($payload['tag_name'] ?? '') === $previewTag;
        if (!is_array($payload) || ($payload['draft'] ?? true)
            || (($payload['prerelease'] ?? true) && !$previewMatches)) {
            throw new RuntimeException('invalid_release');
        }
        if ($previewTag !== '' && !$previewMatches) {
            throw new RuntimeException('unexpected_preview_release');
        }

        $tag = (string) ($payload['tag_name'] ?? '');
        if (!preg_match('/^v(\d+\.\d+\.\d+)$/D', $tag, $match)) {
            throw new RuntimeException('invalid_release_version');
        }
        $assets = $payload['assets'] ?? null;
        if (!is_array($assets)) throw new RuntimeException('missing_release_assets');

        foreach ($assets as $asset) {
            if (!is_array($asset) || ($asset['name'] ?? '') !== self::ZIP_NAME) continue;
            $url = (string) ($asset['browser_download_url'] ?? '');
            $digest = (string) ($asset['digest'] ?? '');
            if ($url !== 'https://github.com/kiddiewise/newtic/releases/download/'.$tag.'/'.self::ZIP_NAME) {
                throw new RuntimeException('untrusted_release_url');
            }
            if (!preg_match('/^sha256:([a-f0-9]{64})$/D', $digest, $sha)) {
                throw new RuntimeException('missing_sha256');
            }

            return [
                'version' => $match[1],
                'tag' => $tag,
                'url' => $url,
                'sha256' => $sha[1],
                'notes' => mb_substr(strip_tags((string) ($payload['body'] ?? '')), 0, 1800),
                'published_at' => (string) ($payload['published_at'] ?? ''),
            ];
        }

        throw new RuntimeException('missing_immutable_release_zip');
    }

    public function writeInstalledVersion(string $version): void
    {
        if (!preg_match('/^\d+\.\d+\.\d+$/D', $version)) {
            throw new RuntimeException('Invalid version.');
        }
        $temp = base_path('.newtic-installed-version.tmp');
        if (file_put_contents($temp, $version."\n", LOCK_EX) === false
            || !rename($temp, base_path('.newtic-installed-version'))) {
            throw new RuntimeException('Installed version could not be recorded.');
        }
    }

    private function writeJson(string $name, array $value): void
    {
        $directory = dirname($this->path($name));
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Update state directory unavailable.');
        }
        $temp = $this->path($name.'.tmp');
        if (file_put_contents($temp, json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), LOCK_EX) === false
            || !rename($temp, $this->path($name))) {
            throw new RuntimeException('Update state could not be saved.');
        }
        @chmod($this->path($name), 0600);
    }
}
