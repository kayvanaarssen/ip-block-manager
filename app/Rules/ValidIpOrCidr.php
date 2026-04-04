<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIpOrCidr implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim($value);

        // Check plain IPv4
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return;
        }

        // Check plain IPv6
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return;
        }

        // Check IPv4 CIDR
        if (preg_match('/^(\d{1,3}\.){3}\d{1,3}\/(\d{1,2})$/', $value)) {
            [$ip, $prefix] = explode('/', $value);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $prefix >= 0 && $prefix <= 32) {
                return;
            }
        }

        // Check IPv6 CIDR
        if (str_contains($value, '/')) {
            [$ip, $prefix] = explode('/', $value, 2);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && is_numeric($prefix) && $prefix >= 0 && $prefix <= 128) {
                return;
            }
        }

        $fail('The :attribute must be a valid IPv4, IPv6 address or CIDR range.');
    }
}
