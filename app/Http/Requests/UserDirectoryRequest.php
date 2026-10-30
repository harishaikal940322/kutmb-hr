<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserDirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware performs the dedicated RBAC check.
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
