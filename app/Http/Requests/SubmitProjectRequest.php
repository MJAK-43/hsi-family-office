<?php

namespace App\Http\Requests;

use App\Support\ProjectAccess;

class SubmitProjectRequest extends ProjectBudgetRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return ProjectAccess::canSubmit($this->user(), $project);
    }

    public function rules(): array
    {
        return [];
    }
}
