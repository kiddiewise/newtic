<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Http\Response;

final class AdminRecoveryController extends Controller
{
    public function show(AdminRecoveryService $recovery): Response
    {
        abort_unless($recovery->active(), 404);

        return response()->view('admin.recovery')
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function reset(Request $request, AdminRecoveryService $recovery): RedirectResponse
    {
        abort_unless($recovery->active(), 404);

        $data = $request->validate([
            'recovery_code' => ['required', 'string', 'min:32', 'max:128'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
        ]);

        $recovery->reset($data['recovery_code'], $data['password']);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'Yönetici şifresi güncellendi. Yeni şifrenizle giriş yapabilirsiniz.');
    }
}
