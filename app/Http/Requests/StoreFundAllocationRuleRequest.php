<?php

namespace App\Http\Requests;

use App\Services\FundService;
use Illuminate\Foundation\Http\FormRequest;

class StoreFundAllocationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage allocation rules');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.fund_account_id' => ['required', 'exists:fund_accounts,id'],
            'items.*.percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $percentages = collect($this->input('items', []))->pluck('percentage')->all();

            try {
                app(FundService::class)->assertRulePercentages($percentages);
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('items', $e->getMessage());
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Au moins une ligne d\'allocation est requise.',
            'items.*.percentage.required' => 'Chaque pourcentage est obligatoire.',
        ];
    }
}
