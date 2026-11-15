<?php

namespace App\Http\Controllers;

use App\Services\StoreInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class SetupWizardController extends Controller
{
    public function index(Request $request, StoreInstaller $installer): View|RedirectResponse
    {
        abort_unless($installer->hasPending() && !$installer->installed(), 404);
        if ($installer->sessionAuthorized($request)) {
            return redirect()->route('install.checks');
        }

        return $this->view('setup.access');
    }

    public function unlock(Request $request, StoreInstaller $installer): RedirectResponse
    {
        abort_unless($installer->hasPending() && !$installer->installed(), 404);
        $data = $request->validate([
            'setup_token' => ['required', 'string', 'size:64', 'regex:/^[0-9a-f]+$/D'],
        ]);
        if (!$installer->validToken($data['setup_token']) || !$installer->pristine()) {
            return back()->withErrors(['setup_token' => 'Kurulum anahtarı geçersiz veya süresi dolmuş.'])
                ->onlyInput();
        }

        $request->session()->regenerate();
        $request->session()->put('newtic_setup_digest', $installer->tokenFingerprint());

        return redirect()->route('install.checks');
    }

    public function checks(Request $request, StoreInstaller $installer): View
    {
        $this->authorizeSetup($request, $installer);

        return $this->view('setup.checks', [
            'checks' => $installer->checks($request),
            'ready' => $installer->ready($request),
        ]);
    }

    public function details(Request $request, StoreInstaller $installer): View|RedirectResponse
    {
        $this->authorizeSetup($request, $installer);
        if (!$installer->ready($request)) {
            return redirect()->route('install.checks');
        }

        return $this->view('setup.details', [
            'domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
        ]);
    }

    public function finish(Request $request, StoreInstaller $installer): RedirectResponse
    {
        $this->authorizeSetup($request, $installer);
        $data = $request->validate([
            'store_name' => ['required', 'string', 'min:2', 'max:120'],
            'admin_name' => ['required', 'string', 'min:3', 'max:160'],
            'admin_email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'accept_safe_mode' => ['required', 'accepted'],
        ]);

        $admin = $installer->complete($data, $request);
        $request->session()->forget('newtic_setup_digest');
        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.index')
            ->with('success', 'NEWTIC mağazası başarıyla kuruldu. Ödeme, kargo ve indeksleme kontrollere kadar kapalıdır.');
    }

    private function authorizeSetup(Request $request, StoreInstaller $installer): void
    {
        abort_unless(
            $installer->hasPending() && !$installer->installed()
                && $installer->sessionAuthorized($request),
            404
        );
    }

    private function view(string $name, array $data = []): View
    {
        return view($name, $data);
    }
}
