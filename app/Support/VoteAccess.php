<?php

namespace App\Support;

use App\Enums\MemberStatus;
use App\Enums\VoteStatus;
use App\Models\User;
use App\Models\Vote;

class VoteAccess
{
    public static function canView(User $user): bool
    {
        return $user->can('view votes') || $user->can('view all votes');
    }

    public static function canViewAll(User $user): bool
    {
        return $user->can('view all votes');
    }

    public static function canCreate(User $user): bool
    {
        return $user->can('create votes');
    }

    public static function canManage(User $user): bool
    {
        return $user->can('manage votes');
    }

    public static function canClose(User $user): bool
    {
        return $user->can('close votes') || $user->can('manage votes');
    }

    public static function canCast(User $user): bool
    {
        if (! $user->can('cast votes')) {
            return false;
        }

        $profile = $user->memberProfile;

        return $profile === null || $profile->status === MemberStatus::Active;
    }

    public static function canCastOnVote(User $user, Vote $vote): bool
    {
        return self::canCast($user)
            && $vote->status === VoteStatus::Open
            && ! $vote->isExpired()
            && $vote->eligibilities()->where('user_id', $user->id)->where('is_eligible', true)->exists();
    }

    public static function canViewBallotsDetail(User $user, ?Vote $vote = null): bool
    {
        // Règlement : seul l'administrateur peut savoir qui a voté quoi.
        return $user->hasRole('super_admin');
    }
}
