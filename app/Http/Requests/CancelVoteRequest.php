<?php

namespace App\Http\Requests;

use App\Enums\VoteStatus;
use App\Support\VoteAccess;
use Illuminate\Foundation\Http\FormRequest;

class CancelVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vote = $this->route('vote');

        return VoteAccess::canManage($this->user())
            && in_array($vote->status, [VoteStatus::Draft, VoteStatus::Scheduled, VoteStatus::Open], true);
    }

    public function rules(): array
    {
        return [];
    }
}
