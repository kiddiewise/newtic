<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    public function index(): View
    {
        return view('admin.bank-accounts.index', [
            'accounts' => BankAccount::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateAccount($request);
        $data['is_active'] = $request->boolean('is_active');
        if ($request->hasFile('logo')) {
            $data['logo_path'] = 'storage/'.$request->file('logo')->store('bank-logos', 'public');
        }
        BankAccount::create($data);

        return redirect()->route('admin.bank-accounts.index')->with('success', 'Banka hesabı eklendi.');
    }

    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $data = $this->validateAccount($request, $bankAccount);
        $data['is_active'] = $request->boolean('is_active');
        $previousLogo = $bankAccount->logo_path;

        if ($request->hasFile('logo')) {
            $data['logo_path'] = 'storage/'.$request->file('logo')->store('bank-logos', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $data['logo_path'] = null;
        }

        $bankAccount->update($data);
        if (array_key_exists('logo_path', $data) && $previousLogo
            && $previousLogo !== $bankAccount->logo_path && str_starts_with($previousLogo, 'storage/')) {
            Storage::disk('public')->delete(substr($previousLogo, 8));
        }

        return back()->with('success', 'Banka hesabı güncellendi.');
    }

    private function validateAccount(Request $request, ?BankAccount $account = null): array
    {
        $iban = $request->input('iban');
        if (is_string($iban)) {
            $request->merge(['iban' => BankAccount::normalizeIban($iban)]);
        }

        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:120'],
            'account_holder' => ['required', 'string', 'max:190'],
            'iban' => [
                'required', 'string', 'size:26', 'regex:/^TR[0-9]{24}$/',
                Rule::unique('bank_accounts', 'iban')->ignore($account?->id),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
            'remove_logo' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048',
                'dimensions:max_width=1600,max_height=1200'],
        ]);

        if (!BankAccount::validTurkishIban($data['iban'])) {
            throw ValidationException::withMessages(['iban' => 'Türkiye IBAN bilgisi veya kontrol hanesi geçersiz.']);
        }

        unset($data['logo'], $data['remove_logo']);
        return $data;
    }
}
