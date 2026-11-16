<?php

namespace App\Http\Requests;

use App\Enums\FundAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFundAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage fund accounts');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:fund_accounts,slug'],
            'type' => ['required', Rule::enum(FundAccountType::class)],
            'description' => ['nullable', 'string'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
        ];
    }
}
