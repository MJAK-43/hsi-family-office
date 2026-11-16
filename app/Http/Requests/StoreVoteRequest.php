<?php

namespace App\Http\Requests;

use App\Enums\MajorityRule;
use App\Enums\VoteStatus;
use App\Enums\VoteType;
use App\Support\VoteAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return VoteAccess::canCreate($this->user());
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::enum(VoteType::class)],
            'project_id' => ['nullable', 'exists:projects,id'],
            'status' => ['nullable', Rule::in([VoteStatus::Draft->value, VoteStatus::Scheduled->value, VoteStatus::Open->value])],
            'opens_at' => ['nullable', 'required_if:status,'.VoteStatus::Scheduled->value, 'date', 'after:now'],
            'closes_at' => ['nullable', 'date', 'after:now'],
            'quorum_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'quorum_absolute' => ['nullable', 'integer', 'min:1'],
            'majority_rule' => ['nullable', Rule::enum(MajorityRule::class)],
            'allow_abstention' => ['nullable', 'boolean'],
            'ballot_visibility' => ['nullable', Rule::in(['named', 'secret'])],
            'options' => ['nullable', 'array', 'min:2', 'max:12'],
            'options.*' => ['required', 'string', 'max:120', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre du vote est obligatoire.',
            'type.required' => 'Le type de vote est obligatoire.',
            'closes_at.after' => 'La date de clôture doit être dans le futur.',
        ];
    }
}
