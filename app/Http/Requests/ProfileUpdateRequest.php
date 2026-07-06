<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Email is intentionally not editable here — it stays fixed once
     * registered, matching the reference app's read-only-email design.
     * Full Profile/Security/Organization tabs (logo uploads, signature,
     * password change) land in Milestone 6.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'organization_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
