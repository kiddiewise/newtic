<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class AddressValidation
{
    public static function rules(): array
    {
        return [
            'address_label' => ['required','string','max:50'],
            'full_name' => ['required','string','min:3','max:160'],
            'phone' => ['required','regex:/^0?5[0-9]{9}$/'],
            'city' => ['required', Rule::in(TurkeyProvinces::ALL)],
            'district' => ['required','string','min:2','max:100'],
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
