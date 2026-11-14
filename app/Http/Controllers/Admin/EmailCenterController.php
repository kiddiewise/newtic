<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailDelivery;
use App\Models\EmailSetting;
use App\Services\EmailTransport;
use App\Support\StoreIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class EmailCenterController extends Controller
{
    public function index(): View
    {
        $settings = EmailSetting::current();
        $deliveries = EmailDelivery::query()->latest('id')->paginate(20);
        $counts = EmailDelivery::query()->selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');
        return view('admin.email-center', compact('settings', 'deliveries', 'counts'));
    }

    public function save(Request $request): RedirectResponse
    {
        $settings = EmailSetting::current();
        $data = $request->validate([
            'smtp_host' => ['required','string','max:255','regex:/^[a-zA-Z0-9.-]+$/D'],
            'smtp_port' => ['required','integer','between:1,65535'],
            'smtp_scheme' => ['required', Rule::in(['smtp','smtps'])],
            'smtp_username' => ['required','string','max:255'],
            'smtp_password' => ['nullable','string','max:2048'],
            'from_address' => ['required','email','max:255'],
            'from_name' => ['required','string','max:140'],
            'reply_to' => ['nullable','email','max:255'],
            'admin_emails' => ['nullable','string','max:2000'],
            'test_recipient' => ['nullable','email','max:255'],
            'review_delay_days' => ['required','integer','between:1,14'],
            'events' => ['nullable','array'],
            'events.*' => ['string',Rule::in(array_keys(EmailSetting::EVENTS))],
            'enabled' => ['required','boolean'],
        ]);
        $emails = array_filter(array_map('trim',
            preg_split('/[,;\r\n]+/', (string) ($data['admin_emails'] ?? '')) ?: []));
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'admin_emails' => 'Yönetici e-posta adreslerinden biri geçersiz.',
                ]);
            }
        }
        if (!$settings->configured() && blank($data['smtp_password'] ?? null)) {
            throw ValidationException::withMessages(['smtp_password' => 'İlk kurulumda SMTP parolası gereklidir.']);
        }
        if (EmailSetting::previewEnvironment() && blank($data['test_recipient'] ?? null)) {
            throw ValidationException::withMessages(['test_recipient' => 'Test ortamı için güvenli alıcı zorunludur.']);
        }
        $connectionKeys = ['smtp_host','smtp_port','smtp_scheme','smtp_username','from_address','from_name','reply_to'];
        $modified = filled($data['smtp_password'] ?? null);
        foreach ($connectionKeys as $key) {
            if ((string) $settings->{$key} !== (string) ($data[$key] ?? '')) $modified = true;
        }
        if ($modified && (bool)$data['enabled']) {
            throw ValidationException::withMessages([
                'enabled' => 'SMTP bilgileri değişti. Önce devre dışı kaydedin, test e-postasını gönderin, ardından etkinleştirin.',
            ]);
        }
        if ((bool)$data['enabled'] && (!$settings->verified_at || $modified)) {
            throw ValidationException::withMessages([
                'enabled' => 'Önce SMTP bağlantısını test etmeniz gerekiyor.',
            ]);
        }
        foreach ($connectionKeys as $key) $settings->{$key} = $data[$key] ?? null;
        if (filled($data['smtp_password'] ?? null)) $settings->smtp_password = $data['smtp_password'];
        $settings->enabled = (bool)$data['enabled'];
        $settings->admin_emails = implode("\n", array_unique($emails));
        $settings->test_recipient = $data['test_recipient'] ?? null;
        $settings->review_delay_days = (int)$data['review_delay_days'];
        $settings->events = array_values(array_unique($data['events'] ?? []));
        if ($modified) $settings->verified_at = null;
        $settings->save();

        return back()->with('success', 'E-posta tercihleri kaydedildi. Şifrelenmiş SMTP parolası ekranda gösterilmez.');
    }

    public function test(Request $request, EmailTransport $transport): RedirectResponse
    {
        $settings = EmailSetting::current();
        if (!$settings->configured()) {
            return back()->withErrors(['smtp_host' => 'Önce SMTP bilgilerini kaydedin.']);
        }
        try {
            $transport->send($settings, (string)$request->user()->email, [
                'subject' => 'NEWTIC · SMTP bağlantı testi',
                'brand' => StoreIdentity::name(),
                'heading' => 'E-posta bağlantınız çalışıyor',
                'body' => 'Bu mesaj yönetim panelinden talep ettiğiniz SMTP bağlantı testidir. Gerçek müşterilere e-posta gönderilmedi.',
                'lines' => ['Test zamanı: '.now()->format('d.m.Y H:i')],
                'action' => null, 'note' => 'NEWTIC yönetim paneli SMTP denemesi.',
            ]);
            $settings->update(['verified_at' => now()]);
        } catch (\Throwable $e) {
            return back()->withErrors(['smtp_host' => 'SMTP bağlantısı başarısız oldu. Host, port, kullanıcı adı, şifre ve e-posta DNS ayarlarını kontrol edin.']);
        }
        return back()->with('success', 'SMTP sunucusu iletiyi kabul etti. Gelen kutusu ve spam klasörünü de kontrol edin; ardından bildirimleri etkinleştirebilirsiniz.');
    }

    public function process(): RedirectResponse
    {
        Artisan::call('email:process', ['--limit' => 20]);
        return back()->with('success', 'Bekleyen e-posta kuyruğu kontrol edildi. Durum kayıtlarını aşağıdan inceleyin.');
    }

    public function retry(EmailDelivery $emailDelivery): RedirectResponse
    {
        if ($emailDelivery->status !== 'failed') {
            return back()->withErrors(['email' => 'Yalnızca başarısız iletiler yeniden sıraya alınabilir.']);
        }
        $emailDelivery->update(['status' => 'retry', 'attempts' => 0,
            'available_at' => now(), 'processing_at' => null, 'last_error' => null]);
        return back()->with('success', 'İleti yeniden deneme kuyruğuna alındı.');
    }
}
