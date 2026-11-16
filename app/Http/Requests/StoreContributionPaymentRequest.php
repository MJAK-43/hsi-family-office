<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Support\ContributionAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContributionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit contribution payments');
    }

    public function rules(): array
    {
        $rules = [
            'contribution_plan_id' => ['nullable', 'exists:contribution_plans,id'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:1'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'kind' => ['nullable', 'in:quarterly,voluntary'],
            'quarter' => ['nullable', 'required_if:kind,quarterly', 'integer', 'between:1,4'],
            'payment_month' => ['nullable', 'integer', 'between:1,12'],
            'payment_year' => ['nullable', 'required_if:kind,quarterly', 'integer', 'between:2020,2100'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:150'],
            'paid_at' => ['nullable', 'required_if:kind,quarterly', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
            'proof' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ];

        if (ContributionAccess::canSelectMember($this->user())) {
            $rules['user_id'] = ['required', 'exists:users,id'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Le montant est obligatoire.',
            'quarter.required_if' => 'Choisissez le trimestre payé.',
            'payment_year.required_if' => 'Indiquez l’année du trimestre.',
            'paid_at.required_if' => 'Indiquez la date du paiement : elle sert au calcul de la pénalité de retard.',
            'paid_at.before_or_equal' => 'La date du paiement ne peut pas être dans le futur.',
            'amount.min' => 'Le montant doit être d\'au moins 1.',
            'currency.size' => 'La devise doit comporter 3 caractères (ex. EUR).',
            'payment_method.required' => 'La méthode de paiement est obligatoire.',
            'proof.max' => 'La preuve ne doit pas dépasser 5 Mo.',
            'proof.mimes' => 'La preuve doit être au format JPG, PNG ou PDF.',
            'user_id.required' => 'Veuillez sélectionner un membre.',
        ];
    }
}
