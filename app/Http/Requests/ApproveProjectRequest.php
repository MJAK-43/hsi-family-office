<?php

namespace App\Http\Requests;

use App\Support\ProjectAccess;

class ApproveProjectRequest extends ProjectBudgetRequest
{
    public function authorize(): bool
    {
        return ProjectAccess::canApprove($this->user());
    }

    public function rules(): array
    {
        return [
            'committee_decision_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
