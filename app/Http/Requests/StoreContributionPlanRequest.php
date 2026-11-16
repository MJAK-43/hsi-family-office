<?php

namespace App\Http\Requests;

use App\Enums\ContributionFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContributionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage contribution plans');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'frequency' => ['required', Rule::enum(ContributionFrequency::class)],
            'country_id' => ['nullable', 'exists:countries,id'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'auto_generate' => ['boolean'],
            'starts_on' => ['nullable', 'date'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:90'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du plan est obligatoire.',
            'amount.min' => 'Le montant ne peut pas être négatif.',
        ];
    }
}
