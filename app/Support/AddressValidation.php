<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class AddressValidation
{
    /**
     * Normalize common Turkish mobile entry formats to 05XXXXXXXXX.
     * Invalid numbers remain invalid and are rejected by validation.
     */
    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s().-]+/u', '', trim($phone)) ?? trim($phone);

        if (preg_match('/^00905[0-9]{9}$/', $phone)) {
            return '0'.substr($phone, -10);
        }

        if (preg_match('/^\+?905[0-9]{9}$/', $phone)) {
            return '0'.substr($phone, -10);
        }

        if (preg_match('/^5[0-9]{9}$/', $phone)) {
            return '0'.$phone;
        }

        return $phone;
    }

    /**
     * Show only the 10-digit national mobile number; the +90 prefix is fixed
     * outside the field. Keep invalid old input unchanged for correction.
     */
    public static function formatForEntry(?string $phone): string
    {
        $original = trim((string) $phone);
        $normalized = self::normalizePhone($original);

        if (! preg_match('/^05[0-9]{9}$/', $normalized)) {
            return $original;
        }

        $number = substr($normalized, 1);

        return substr($number, 0, 3).' '.substr($number, 3, 3).' '
            .substr($number, 6, 2).' '.substr($number, 8, 2);
    }

    public static function normalizePhoneInput(\Illuminate\Http\Request $request): void
    {
        $phone = $request->input('phone');
        if (is_string($phone)) {
            $request->merge(['phone' => self::normalizePhone($phone)]);
        }
    }

    public static function rules(): array
    {
        return [
            'address_label' => ['required','string','max:50'],
            'full_name' => ['required','string','min:3','max:160'],
            'phone' => ['required','regex:/^0?5[0-9]{9}$/'],
            'city' => ['required', Rule::in(TurkeyProvinces::ALL)],
            'district' => ['required','string','min:2','max:100',
                Rule::in(TurkeyDistricts::forProvince((string) request()->input('city', '')))],
            'street_address' => ['required','string','min:10','max:1200'],
            'invoice_type' => ['required', Rule::in(['individual','corporate'])],
            'company_title' => ['exclude_unless:invoice_type,corporate','required_if:invoice_type,corporate','nullable','string','max:200'],
            'tax_office' => ['exclude_unless:invoice_type,corporate','required_if:invoice_type,corporate','nullable','string','max:100'],
            'tax_number' => ['exclude_unless:invoice_type,corporate','required_if:invoice_type,corporate','nullable','regex:/^[0-9]{10,11}$/'],
        ];
    }

    public static function clean(array $data): array
    {
        $data = array_intersect_key($data, array_flip([
            'address_label','full_name','phone','city','district','street_address',
            'invoice_type','company_title','tax_office','tax_number',
        ]));
        if (($data['invoice_type'] ?? 'individual') === 'individual') {
            $data['company_title'] = null;
            $data['tax_office'] = null;
            $data['tax_number'] = null;
        }
        $data['phone'] = preg_replace('/\s+/', '', $data['phone']);
        return $data;
    }
}
