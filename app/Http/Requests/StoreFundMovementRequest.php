<?php

namespace App\Http\Requests;

use App\Enums\FundMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFundMovementRequest extends FormRequest
{
    private const ALLOWED_TYPES = [
        FundMovementType::ManualIncome,
        FundMovementType::ManualExpense,
        FundMovementType::Adjustment,
    ];

    public function authorize(): bool
    {
        return $this->user()->can('create fund movements');
    }

    public function rules(): array
    {
        return [
            'fund_account_id' => ['required', 'exists:fund_accounts,id'],
            'type' => ['required', Rule::in(array_map(fn ($t) => $t->value, self::ALLOWED_TYPES))],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:1'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'direction' => ['required', 'in:credit,debit'],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:150'],
            'movement_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Le montant doit être d\'au moins 1.',
            'label.required' => 'Le libellé est obligatoire.',
            'type.in' => 'Type de mouvement non autorisé.',
        ];
    }
}
