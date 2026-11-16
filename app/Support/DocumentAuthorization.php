<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DocumentAuthorization
{
    public static function canManage(User $user): bool { return $user->can('manage documents'); }
    public static function canPublish(User $user): bool { return $user->can('publish documents'); }
    public static function canManageAccess(User $user): bool { return $user->can('manage document access'); }

    public static function visibleQuery(User $user): Builder
    {
        $query = Document::query();
        if (self::canManage($user)) return $query;
        $roleIds = $user->roles()->pluck('roles.id');
        return $query->where('status', DocumentStatus::Published)->where(function (Builder $access) use ($user, $roleIds) {
            $access->whereIn('visibility', ['public', 'members'])
                ->orWhereHas('accesses', fn (Builder $grant) => $grant
                    ->where('can_download', true)
                    ->where(function (Builder $target) use ($user, $roleIds) {
                        $target->where('user_id', $user->id)->orWhereIn('role_id', $roleIds);
                    }));
        });
    }

    public static function canView(User $user, Document $document): bool
    {
        if (! $user->can('view document library')) return false;
        if (self::canManage($user)) return true;
        return self::visibleQuery($user)->whereKey($document->id)->exists();
    }

    public static function canDownload(User $user, Document $document): bool
    {
        return self::canView($user, $document)
            && $document->currentVersion?->scan_status === 'clean';
    }
}
