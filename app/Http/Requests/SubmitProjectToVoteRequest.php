<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Support\ProjectAccess;

class SubmitProjectToVoteRequest extends ProjectBudgetRequest
{
    public function authorize(): bool
    {
        if (! ProjectAccess::canSubmitToVote($this->user())) {
            return false;
        }

        $project = $this->route('project');

        return $project->status === ProjectStatus::ApprovedByCommittee;
    }

    public function rules(): array
    {
        return [];
    }
}
