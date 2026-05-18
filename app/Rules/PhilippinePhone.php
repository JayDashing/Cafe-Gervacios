<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class PhilippinePhone implements Rule
{
    public function passes($attribute, $value): bool
    {
        $normalized = preg_replace('/[\s\-\(\)\.]/', '', (string) $value) ?? '';

        return preg_match('/^(09\d{9}|\+639\d{9}|639\d{9})$/', $normalized) === 1;
    }

    public function message(): string
    {
        return 'Enter a valid PH number (+63XXXXXXXXX or 09XXXXXXXXX).';
    }
}
