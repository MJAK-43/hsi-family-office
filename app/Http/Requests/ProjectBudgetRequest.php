<?php

namespace App\Http\Requests;

use App\Enums\ProjectSector;
use App\Models\ProjectType;
use App\Support\FundRules;
use App\Services\ProjectService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ProjectBudgetRequest extends FormRequest
{
    protected function selectedType(): ?ProjectType
    {
        return $this->input('project_type_id') ? ProjectType::find($this->input('project_type_id')) : null;
    }

    /** Pour un prêt, pas de budget distinct ni d'apport : le budget est le montant demandé. */
    protected function prepareForValidation(): void
    {
        if ($this->selectedType()?->is_loan) {
            $this->merge([
                'total_budget' => $this->input('requested_amount'),
                'personal_contribution' => 0,
                'sector' => $this->input('sector') ?: 'other',
                'currency' => $this->input('currency') ?: \App\Support\FundCurrency::code(),
            ]);
        }
    }

    protected function budgetRules(bool $required = true): array
    {
        $requiredRule = $required ? 'required' : 'sometimes';
        $isLoan = (bool) $this->selectedType()?->is_loan;
        $maxDuration = FundRules::get('loan_max_duration_months');

        return [
            'project_type_id' => ['nullable', Rule::exists('project_types', 'id')->where('is_active', true)],
            // Compte de fonds qui versera l'argent (obligatoire à la création).
            'fund_account_id' => [$required ? 'required' : 'sometimes', Rule::exists('fund_accounts', 'id')->where('is_active', true)->whereNot('slug', 'treasury')],
            'resubmission_of_id' => ['nullable', Rule::exists('projects', 'id')->where('owner_id', $this->user()->id)->whereIn('status', ['rejected_by_committee', 'rejected_by_family'])],
            'repayment_plan' => [$isLoan ? 'required' : 'nullable', 'string', 'max:5000'],
            'loan_duration_months' => [$isLoan ? 'required' : 'nullable', 'integer', 'min:1', 'max:'.$maxDuration],
            'presentation_date' => [$isLoan ? 'required' : 'nullable', 'date', 'after_or_equal:today'],
            'title' => [$requiredRule, 'string', 'max:180'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'city' => ['nullable', 'string', 'max:120'],
            'sector' => [$requiredRule, Rule::enum(ProjectSector::class)],
            'total_budget' => [$requiredRule, 'numeric', 'decimal:0,2', 'min:1'],
            'personal_contribution' => [$requiredRule, 'numeric', 'decimal:0,2', 'min:0'],
            'requested_amount' => [$requiredRule, 'numeric', 'decimal:0,2', 'min:0'],
            'currency' => [$requiredRule, 'string', 'regex:/^[A-Za-z]{3}$/'],
            'expected_duration_months' => ['nullable', 'integer', 'min:1', 'max:240'],
            'expected_roi_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function withBudgetValidation(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $type = $this->selectedType();
            if ($type && ! $type->requires_personal_contribution) {
                if ((float) $this->input('requested_amount') <= 0) {
                    $validator->errors()->add('requested_amount', 'Le montant demandé doit être positif.');
                }

                return;
            }

            try {
                ProjectService::validateBudgetRule(
                    $this->input('total_budget', 0),
                    $this->input('personal_contribution', 0),
                    $this->input('requested_amount', 0),
                    $this->string('currency')->toString(),
                );
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('requested_amount', $e->getMessage());
            }
        });
    }

    /** Pièces jointes envoyées avec le formulaire du projet (création ou modification). */
    protected function attachmentRules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'attachments.*.type' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\ProjectDocumentType::class)],
            'attachments.*.title' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'fund_account_id.required' => 'Choisissez le compte de fonds qui financera le projet.',
            'attachments.*.file.max' => 'Chaque pièce jointe doit faire moins de 10 Mo.',
            'attachments.*.file.mimes' => 'Pièce jointe : format non autorisé (PDF, images, Word, Excel).',
            'attachments.*.type.required' => 'Choisissez le type de chaque pièce jointe.',
            'title.required' => 'Le titre du projet est obligatoire.',
            'title.max' => 'Le titre ne peut pas dépasser 180 caractères.',
            'sector.required' => 'Le secteur est obligatoire.',
            'total_budget.required' => 'Le budget total est obligatoire.',
            'total_budget.min' => 'Le budget total doit être au moins de 1.',
            'personal_contribution.required' => 'L\'apport personnel est obligatoire.',
            'requested_amount.required' => 'Le montant demandé est obligatoire.',
            'currency.size' => 'La devise doit comporter 3 caractères.',
            'repayment_plan.required' => 'Décrivez le plan de remboursement proposé.',
            'loan_duration_months.required' => 'Indiquez la durée de remboursement.',
            'loan_duration_months.max' => 'La durée de remboursement ne peut pas dépasser :max mois.',
            'presentation_date.required' => 'Indiquez la date prévue de présentation au groupe.',
            'resubmission_of_id.exists' => 'Seul un de vos projets refusés peut être redéposé.',
        ];
    }
}
