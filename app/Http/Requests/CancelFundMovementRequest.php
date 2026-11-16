<?php

namespace App\Http\Requests;

use App\Enums\FundMovementStatus;
use App\Models\FundMovement;
use Illuminate\Foundation\Http\FormRequest;

class CancelFundMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var FundMovement $movement */
        $movement = $this->route('fundMovement');

        return $this->user()->can('create fund movements')
            && $movement->status !== FundMovementStatus::Cancelled;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Le motif d\'annulation est obligatoire.',
            'cancellation_reason.min' => 'Le motif doit contenir au moins 5 caractères.',
        ];
    }
}
