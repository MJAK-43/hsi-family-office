<?php

namespace App\Http\Requests;

use App\Support\ProjectAccess;

class EvaluateProjectRequest extends ProjectBudgetRequest
{
    public function authorize(): bool
    {
        return ProjectAccess::canEvaluate($this->user());
    }

    public function rules(): array
    {
        return [
            'profitability_score' => ['nullable', 'integer', 'min:0', 'max:20'],
            'risk_score' => ['nullable', 'integer', 'min:0', 'max:20'],
            'founder_score' => ['nullable', 'integer', 'min:0', 'max:20'],
            'family_impact_score' => ['nullable', 'integer', 'min:0', 'max:20'],
            'sustainability_score' => ['nullable', 'integer', 'min:0', 'max:20'],
            'values_alignment_score' => ['nullable', 'integer', 'min:0', 'max:20'],
            'recommendation' => ['nullable', 'string', 'max:500'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.integer' => 'Chaque score doit être un entier.',
            '*.min' => 'Chaque score doit être au minimum 0.',
            '*.max' => 'Chaque score doit être au maximum 20.',
        ];
    }
}
