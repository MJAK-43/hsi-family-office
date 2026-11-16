<?php

namespace App\Http\Requests;

use App\Enums\MemberStatus;
use App\Support\MemberRoleHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateMemberRequest extends FormRequest
{
    /** Les anciennes saisies libres (« Masculin », « Non précisé »…) sont converties avant contrôle. */
    protected function prepareForValidation(): void
    {
        if ($this->has('gender')) {
            $this->merge(['gender' => \App\Support\Gender::normalize($this->input('gender'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('edit members');
    }

    public function rules(): array
    {
        $target = $this->route('memberProfile');
        $userId = $target->user_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array', 'min:1', MemberRoleHelper::rolesRule($this->user(), $target->user)],
            'roles.*' => ['string', 'distinct'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'zone_id' => ['nullable', 'exists:zones,id'],
            'family_branch_id' => ['nullable', 'exists:family_branches,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(\App\Support\Gender::CODES)],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:50'],
            'member_since' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(MemberStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.required' => 'Choisissez au moins un rôle.',
            'email.unique' => 'Cet email est déjà utilisé.',
        ];
    }

}
