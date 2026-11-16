<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantLegalPage;
use App\Models\MerchantLegalProfile;
use App\Support\LegalReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LegalSettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.legal-settings', [
            'profile' => MerchantLegalProfile::current(),
            'pages' => MerchantLegalPage::query()->get()->keyBy('slug'),
            'pageLabels' => MerchantLegalPage::LABELS,
            'missing' => LegalReadiness::requiredBeforeLive(),
        ]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:220'],
            'registered_address' => ['nullable', 'string', 'max:2000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:35'],
            'kep_address' => ['nullable', 'email', 'max:255'],
            'mersis_number' => ['nullable', 'string', 'max:40'],
            'tax_office' => ['nullable', 'string', 'max:130'],
            'tax_number' => ['nullable', 'string', 'max:30'],
            'support_hours' => ['nullable', 'string', 'max:160'],
            'show_paytr_disclosure' => ['required', 'boolean'],
        ]);

        MerchantLegalProfile::query()->updateOrCreate(['id' => 1], $data);

        return redirect()->route('admin.legal.index')
            ->with('success', 'Satıcı ve iletişim bilgileri kaydedildi.');
    }

    public function savePage(Request $request, string $slug): RedirectResponse
    {
        abort_unless(array_key_exists($slug, MerchantLegalPage::LABELS), 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:50000'],
            'is_published' => ['required', 'boolean'],
        ]);
        $title = trim($data['title']);
        $body = trim((string) ($data['body'] ?? ''));
        $publish = (bool) $data['is_published'];

        if ($publish && mb_strlen($body) < 200) {
            return back()->withErrors([
                'body' => 'Bu sayfayı yayınlamak için işletmenize özel, incelenmiş en az 200 karakterlik içerik girin.',
            ])->withInput();
        }

        $existing = MerchantLegalPage::query()->where('slug', $slug)->first();
        $changed = !$existing || $existing->title !== $title || $existing->body !== $body;
        MerchantLegalPage::query()->updateOrCreate(['slug' => $slug], [
            'title' => $title,
            'body' => $body,
            'version' => $existing ? $existing->version + (int) $changed : 1,
            'is_published' => $publish,
            'published_at' => $publish ? ($changed || !$existing?->is_published ? now() : $existing->published_at) : null,
        ]);

        return redirect()->route('admin.legal.index', ['#' => $slug])
            ->with('success', $publish ? 'Yasal sayfa yayınlandı. Metin hukuki onaydan geçirilmeli.' : 'Sayfa taslak olarak kaydedildi.');
    }
}
