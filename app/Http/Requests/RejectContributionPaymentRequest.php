<?php

namespace App\Http\Requests;

use App\Models\ContributionPayment;
use App\Support\ContributionAccess;
use Illuminate\Foundation\Http\FormRequest;

class RejectContributionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ContributionPayment $payment */
        $payment = $this->route('contributionPayment');

        return $this->user()->can('reject contribution payments')
            && ContributionAccess::isValidatable($payment->status);
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Le motif du refus est obligatoire.',
            'rejection_reason.min' => 'Le motif du refus doit contenir au moins 5 caractères.',
            'rejection_reason.max' => 'Le motif du refus ne doit pas dépasser 1000 caractères.',
        ];
    }
}
