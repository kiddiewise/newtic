<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class TurkishIdentityNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{10}$/D', $value)) {
            $fail('T.C. kimlik numarası 11 haneli ve geçerli biçimde olmalıdır.');
            return;
        }
        $digits = array_map('intval', str_split($value));
        $odd = $digits[0]+$digits[2]+$digits[4]+$digits[6]+$digits[8];
        $even = $digits[1]+$digits[3]+$digits[5]+$digits[7];
        if ((($odd*7-$even)%10+10)%10 !== $digits[9] || array_sum(array_slice($digits,0,10))%10 !== $digits[10]) {
            $fail('T.C. kimlik numarası kontrolü başarısız. Numarayı düzeltin veya boş bırakın.');
        }
    }
}
