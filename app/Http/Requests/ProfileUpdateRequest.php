<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // Champs du profil membre (ignorés pour un compte sans profil, comme un compte système).
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'country_id' => ['nullable', Rule::exists('countries', 'id')->where('is_active', true)],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::in(['female', 'male', 'other'])],
            'bio' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Saisissez un numéro international valide : choisissez l\'indicatif du pays, puis votre numéro.',
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre compte.',
            'country_id.exists' => 'Choisissez votre pays dans la liste.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom complet',
            'city' => 'ville',
            'date_of_birth' => 'date de naissance',
        ];
    }
}
