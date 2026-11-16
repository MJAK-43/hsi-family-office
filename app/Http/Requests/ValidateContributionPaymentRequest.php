<?php

namespace App\Http\Requests;

use App\Enums\ContributionPaymentStatus;
use App\Models\ContributionPayment;
use Illuminate\Foundation\Http\FormRequest;

class ValidateContributionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ContributionPayment $payment */
        $payment = $this->route('contributionPayment');

        return $this->user()->can('validate contribution payments')
            && in_array($payment->status, [
                ContributionPaymentStatus::Pending,
                ContributionPaymentStatus::Late,
                ContributionPaymentStatus::Validated,
            ], true);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'min:8', 'max:150', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }
}
