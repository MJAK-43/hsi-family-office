<?php

namespace App\Http\Requests;

use App\Support\VoteAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CastBallotRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vote = $this->route('vote');

        return VoteAccess::canCastOnVote($this->user(), $vote);
    }

    public function rules(): array
    {
        $vote = $this->route('vote');
        $values = $vote->options()->pluck('value')->all();

        return [
            'option' => ['required', 'string', Rule::in($values)],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'option.required' => 'Vous devez choisir une option.',
            'option.in' => 'Option de vote invalide.',
        ];
    }
}
