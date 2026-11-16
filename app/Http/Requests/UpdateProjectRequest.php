<?php

namespace App\Http\Requests;

use App\Support\ProjectAccess;
use Illuminate\Contracts\Validation\Validator;

class UpdateProjectRequest extends ProjectBudgetRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return ProjectAccess::canModify($this->user(), $project);
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
