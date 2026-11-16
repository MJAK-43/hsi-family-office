<?php

namespace App\Http\Requests;

use App\Enums\VoteStatus;
use App\Support\VoteAccess;
use Illuminate\Foundation\Http\FormRequest;

class CloseVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vote = $this->route('vote');

        return VoteAccess::canClose($this->user())
            && $vote->status === VoteStatus::Open;
    }

    public function rules(): array
    {
        return [];
    }
}
