<?php

namespace App\Support;

use App\Enums\ContributionPaymentStatus;
use App\Models\ContributionPayment;
use App\Models\User;

class ContributionAccess
{
    public static function isValidatable(ContributionPaymentStatus $status): bool
    {
        return in_array($status, [
            ContributionPaymentStatus::Pending,
            ContributionPaymentStatus::Late,
        ], true);
    }

    public static function canModifyPayment(User $user, ContributionPayment $payment): bool
    {
        if ($payment->status === ContributionPaymentStatus::Validated) {
            return false;
        }

        if ($user->can('edit contributions')) {
            return true;
        }

        return $payment->user_id === $user->id
            && $user->can('submit contribution payments')
            && $payment->isEditableByMember();
    }

    public static function canViewAll(User $user): bool
    {
        return $user->can('view all contribution payments');
    }

    public static function canViewOwn(User $user): bool
    {
        return $user->can('view own contribution payments');
    }

    public static function canValidate(User $user): bool
    {
        return $user->can('validate contribution payments');
    }

    public static function canReject(User $user): bool
    {
        return $user->can('reject contribution payments');
    }

    public static function canManagePlans(User $user): bool
    {
        return $user->can('manage contribution plans');
    }

    public static function canViewProof(User $user, int $paymentOwnerId): bool
    {
        if ($user->id === $paymentOwnerId) {
            return true;
        }

        return $user->can('view all contribution payments')
            || $user->can('validate contribution payments');
    }

    public static function canSelectMember(User $user): bool
    {
        return $user->can('create contributions') && self::canViewAll($user);
    }
}
