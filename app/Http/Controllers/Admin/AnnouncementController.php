<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopAnnouncement;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    private const ZONE = 'Europe/Istanbul';

    public function index(): View
    {
        return view('admin.announcements', [
            'announcements' => ShopAnnouncement::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'string', 'max:5000'],
            'is_active' => ['nullable','boolean'],
            'sort_order' => ['required','integer','min:0','max:9999'],
            'starts_at' => ['nullable','date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable','date_format:Y-m-d\TH:i'],
        ]);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $data['messages'])),
            fn ($line) => $line !== ''));
        if (!$lines || count($lines) > 12 || collect($lines)->contains(fn ($line) => mb_strlen($line) > 240)) {
            throw ValidationException::withMessages([
                'messages' => '1–12 satır girin; her duyuru en fazla 240 karakter olabilir.',
            ]);
        }

        [$starts, $ends] = $this->dates($data);
        foreach ($lines as $index => $line) {
            ShopAnnouncement::create([
                'message' => $line,
                'sort_order' => (int) $data['sort_order'] + $index,
                'is_active' => $request->boolean('is_active'),
                'starts_at' => $starts,
                'ends_at' => $ends,
            ]);
        }
        return back()->with('success', count($lines).' duyuru eklendi.');
    }

    public function update(Request $request, ShopAnnouncement $announcement): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:240'],
            'is_active' => ['nullable','boolean'],
            'sort_order' => ['required','integer','min:0','max:9999'],
            'starts_at' => ['nullable','date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable','date_format:Y-m-d\TH:i'],
        ]);
        [$starts, $ends] = $this->dates($data);
        $announcement->update([
            'message' => trim($data['message']),
            'sort_order' => (int) $data['sort_order'],
            'is_active' => $request->boolean('is_active'),
            'starts_at' => $starts,
            'ends_at' => $ends,
        ]);
        return back()->with('success', 'Duyuru güncellendi.');
    }

    public function destroy(ShopAnnouncement $announcement): RedirectResponse
    {
        $announcement->delete();
        return back()->with('success', 'Duyuru silindi.');
    }

    private function dates(array $data): array
    {
        $start = !empty($data['starts_at'])
            ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['starts_at'], self::ZONE)->utc()
            : null;
        $end = !empty($data['ends_at'])
            ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['ends_at'], self::ZONE)->utc()
            : null;
        if ($start && $end && $end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Bitiş saati başlangıç saatinden sonra olmalıdır.',
            ]);
        }
        return [$start, $end];
    }
}
