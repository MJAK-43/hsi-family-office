<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Country;
use App\Support\MemberRoleHelper;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Page « Mon profil » : toutes les informations de l'adhérent, modifiables par lui-même
     * (sauf le rôle, le statut et la date d'adhésion, gérés par l'administration).
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->load(['roles', 'memberProfile.country', 'memberProfile.familyBranch']);
        $profile = $user->memberProfile;
        $role = MemberRoleHelper::primary($user->roles->pluck('name'));

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'roles' => MemberRoleHelper::sort($user->roles->pluck('name')),
                'role_label' => MemberRoleHelper::labels($user->roles->pluck('name')),
                'avatar_url' => $user->avatarUrl(),
                'created_at' => $user->created_at?->timezone('Europe/Paris')->format('d/m/Y'),
            ],
            'profile' => $profile ? [
                'id' => $profile->id,
                'phone' => $profile->phone,
                'country_id' => $profile->country_id,
                'country' => $profile->country?->name,
                'country_code' => $profile->country?->code,
                'city' => $profile->city,
                'address' => $profile->address,
                'profession' => $profile->profession,
                'date_of_birth' => $profile->date_of_birth?->format('Y-m-d'),
                'gender' => $profile->gender,
                'bio' => $profile->bio,
                'member_since' => $profile->member_since?->format('d/m/Y'),
                'family_branch' => $profile->familyBranch?->name,
                'status' => $profile->status?->value,
                'status_label' => $profile->status?->label(),
                'status_variant' => $profile->status?->badgeVariant(),
            ] : null,
            'countries' => Country::forDisplay(Country::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        if ($profile = $user->memberProfile) {
            $profile->update(collect($data)->only([
                'phone', 'country_id', 'city', 'address', 'profession', 'date_of_birth', 'gender', 'bio',
            ])->all());
        }

        return Redirect::route('profile.edit')->with('success', __('Votre profil a été mis à jour.'));
    }
}
