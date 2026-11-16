<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;

class FundProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $this->user()->can('fund projects')
            && in_array($project->status, [ProjectStatus::ApprovedByFamily, ProjectStatus::Funded], true);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'min:8', 'max:150', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }
}
