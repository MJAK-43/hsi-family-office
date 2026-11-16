<?php

namespace App\Http\Requests;

use App\Support\ProjectAccess;

class RejectProjectRequest extends ProjectBudgetRequest
{
    public function authorize(): bool
    {
        return ProjectAccess::canReject($this->user());
    }

    public function rules(): array
    {
        return [
            'rejected_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejected_reason.required' => 'Le motif de refus est obligatoire.',
            'rejected_reason.min' => 'Le motif de refus doit comporter au moins 5 caractères.',
        ];
    }
}
