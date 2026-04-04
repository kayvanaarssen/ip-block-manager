<?php

namespace App\Http\Requests;

use App\Rules\ValidIpOrCidr;
use Illuminate\Foundation\Http\FormRequest;

class StoreBlockedIpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ip_address' => ['required', 'string', new ValidIpOrCidr],
            'server_ids' => ['required', 'array', 'min:1'],
            'server_ids.*' => ['required', 'exists:servers,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
