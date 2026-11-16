<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferFundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transfer funds');
    }

    public function rules(): array
    {
        return [
            'from_fund_account_id' => ['required', 'exists:fund_accounts,id'],
            'to_fund_account_id' => ['required', 'exists:fund_accounts,id', 'different:from_fund_account_id'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:1'],
            'description' => ['required', 'string', 'min:5', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'min:8', 'max:150', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_fund_account_id.different' => 'Le compte destination doit être différent du compte source.',
            'amount.min' => 'Le montant doit être d\'au moins 1.',
            'description.min' => 'La description doit contenir au moins 5 caractères.',
        ];
    }
}
