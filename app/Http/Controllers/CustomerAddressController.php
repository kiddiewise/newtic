<?php

namespace App\Http\Controllers;

use App\Models\CustomerAddress;
use App\Support\AddressValidation;
use App\Support\TurkeyProvinces;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerAddressController extends Controller
{
    public function index(Request $request): View
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->latest()->get();
        return view('store.addresses', compact('addresses'));
    }

    public function create(): View
    {
        return view('store.address-form', ['address' => new CustomerAddress(), 'provinces' => TurkeyProvinces::ALL]);
    }

    public function edit(Request $request, CustomerAddress $address): View
    {
        $this->own($request, $address);
        return view('store.address-form', ['address' => $address, 'provinces' => TurkeyProvinces::ALL]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = AddressValidation::clean($request->validate(array_merge(AddressValidation::rules(), [
            'is_default' => ['nullable','boolean'],
        ])));
        $default = $request->boolean('is_default') || !$request->user()->addresses()->exists();
        DB::transaction(function () use ($request, $data, $default) {
            if ($default) $request->user()->addresses()->update(['is_default' => false]);
            $request->user()->addresses()->create($data + ['is_default' => $default]);
        });
        return redirect()->route('account.addresses.index')->with('success', 'Adres kaydedildi.');
    }

    public function update(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->own($request, $address);
        $data = AddressValidation::clean($request->validate(array_merge(AddressValidation::rules(), [
            'is_default' => ['nullable','boolean'],
        ])));
        $default = $request->boolean('is_default');
        DB::transaction(function () use ($request, $data, $default, $address) {
            if ($default) $request->user()->addresses()->update(['is_default' => false]);
            $address->update($data + ['is_default' => $default || $address->is_default]);
        });
        return redirect()->route('account.addresses.index')->with('success', 'Adres güncellendi.');
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->own($request, $address);
        $wasDefault = $address->is_default;
        $address->delete();
        if ($wasDefault) {
            $request->user()->addresses()->oldest('id')->first()?->update(['is_default' => true]);
        }
        return redirect()->route('account.addresses.index')->with('success', 'Adres silindi.');
    }

    private function own(Request $request, CustomerAddress $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }
}
