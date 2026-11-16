<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

class StoreProjectRequest extends ProjectBudgetRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create projects');
    }

    public function rules(): array
    {
        return [...$this->budgetRules(), ...$this->attachmentRules()];
    }

    public function withValidator(Validator $validator): void
    {
        $this->withBudgetValidation($validator);
    }
}
