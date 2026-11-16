<?php

namespace App\Http\Requests;

use App\Enums\ProjectDocumentType;
use App\Support\ProjectAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return ProjectAccess::canUploadDocument($this->user(), $project)
            || ProjectAccess::canAttachCommitteeDocument($this->user(), $project);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ProjectDocumentType::class)],
            'title' => ['nullable', 'string', 'max:150'],
            'visibility' => ['nullable', 'in:all,committee'],
            'document' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le type de document est obligatoire.',
            'document.required' => 'Le fichier est obligatoire.',
            'document.max' => 'Le fichier ne peut pas dépasser 10 Mo.',
            'document.mimes' => 'Format de fichier non autorisé.',
        ];
    }
}
