<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UrlRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class UrlRedirectController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) > 120) abort(422);
        $entries = UrlRedirect::query()->when($term !== '', function ($q) use ($term) {
            $safe = '%'.addcslashes($term, '%_\\').'%';
            $q->where('old_path', 'like', $safe)->orWhere('new_path', 'like', $safe);
        })->latest('id')->paginate(25)->withQueryString();
        return view('admin.url-redirects', [
            'entries' => $entries,
            'term' => $term,
            'count' => UrlRedirect::query()->count(),
            'active' => UrlRedirect::query()->where('is_active', true)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'old_path' => ['required', 'string', 'max:2048'],
            'new_path' => ['required', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        try {
            [$old, $new] = $this->pair($data['old_path'], $data['new_path']);
            DB::transaction(function () use ($old, $new, $request): void {
                $existing = UrlRedirect::query()->where('old_path', $old)->lockForUpdate()->first();
                if ($existing) {
                    throw new \InvalidArgumentException('Bu eski URL zaten kayıtlı; mevcut yönlendirmeyi düzenleyin.');
                }
                $pairs = UrlRedirect::query()->where('is_active', true)->pluck('new_path', 'old_path')->all();
                if ($request->boolean('is_active', true)) {
                    $pairs[$old] = $new;
                    UrlRedirect::assertAcyclic($pairs);
                }
                UrlRedirect::create([
                    'old_path' => $old, 'new_path' => $new, 'is_active' => $request->boolean('is_active', true),
                ]);
            }, 3);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['old_path' => $e->getMessage()]);
        }
        return back()->with('success', '301 yönlendirmesi kaydedildi.');
    }

    public function update(Request $request, UrlRedirect $redirect): RedirectResponse
    {
        $data = $request->validate([
            'new_path' => ['required', 'string', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ]);
        try {
            [$old, $new] = $this->pair($redirect->old_path, $data['new_path']);
            DB::transaction(function () use ($redirect, $old, $new, $data): void {
                $row = UrlRedirect::query()->whereKey($redirect->id)->lockForUpdate()->firstOrFail();
                $pairs = UrlRedirect::query()->where('is_active', true)->pluck('new_path', 'old_path')->all();
                unset($pairs[$old]);
                if ((bool) $data['is_active']) {
                    $pairs[$old] = $new;
                    UrlRedirect::assertAcyclic($pairs);
                }
                $row->update(['new_path' => $new, 'is_active' => (bool) $data['is_active']]);
            }, 3);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['new_path' => $e->getMessage()]);
        }
        return back()->with('success', '301 yönlendirmesi güncellendi.');
    }

    public function destroy(UrlRedirect $redirect): RedirectResponse
    {
        $redirect->delete();
        return back()->with('success', '301 yönlendirmesi silindi.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['csv' => ['required', 'file', 'extensions:csv,txt', 'max:3072']]);
        $file = $request->file('csv');
        $stream = fopen($file->getRealPath(), 'rb');
        if (!$stream) throw ValidationException::withMessages(['csv' => 'Dosya okunamadı.']);
        $pairs = [];
        try {
            $header = fgetcsv($stream, 0, ';', '"', '');
            if (is_array($header) && isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            if ($header !== ['old_path', 'new_path']) {
                throw new \InvalidArgumentException('CSV başlıkları old_path;new_path olmalıdır.');
            }
            while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) {
                if ($row === [null]) continue;
                if (count($row) !== 2 || count($pairs) >= 500) {
                    throw new \InvalidArgumentException('En fazla 500 yönlendirme ve iki sütun kabul edilir.');
                }
                [$old, $new] = $this->pair((string) $row[0], (string) $row[1]);
                if (array_key_exists($old, $pairs)) {
                    throw new \InvalidArgumentException('CSV dosyasında tekrar eden adres: '.$old);
                }
                $pairs[$old] = $new;
            }
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['csv' => $e->getMessage()]);
        } finally {
            fclose($stream);
        }
        if (!$pairs) throw ValidationException::withMessages(['csv' => 'İçe aktarılacak yönlendirme bulunamadı.']);

        try {
            DB::transaction(function () use ($pairs): void {
                $existing = UrlRedirect::query()->lockForUpdate()->get();
                $map = $existing->where('is_active', true)->pluck('new_path', 'old_path')->all();
                foreach ($pairs as $old => $new) $map[$old] = $new;
                UrlRedirect::assertAcyclic($map);
                foreach ($pairs as $old => $new) {
                    UrlRedirect::query()->updateOrCreate(['old_path' => $old], [
                        'new_path' => $new, 'is_active' => true,
                    ]);
                }
            }, 3);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['csv' => $e->getMessage()]);
        }

        return back()->with('success', count($pairs).' adet 301 yönlendirmesi içe aktarıldı.');
    }

    private function pair(string $rawOld, string $rawNew): array
    {
        $old = UrlRedirect::normalize($rawOld);
        $new = UrlRedirect::normalize($rawNew);
        UrlRedirect::validatePair($old, $new);
        return [$old, $new];
    }
}