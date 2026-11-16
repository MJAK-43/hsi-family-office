<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Models\Country;
use App\Models\MemberProfile;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\Membership\ApplicationDecision;
use App\Notifications\Membership\ApplicationReceived;
use App\Notifications\Membership\ExistingAccountNotice;
use App\Notifications\Membership\NewApplicationForAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MembershipApplicationController extends Controller
{
    /** Formulaire public de demande d'adhésion. */
    public function create(Request $request): Response
    {
        $google = $request->session()->get(GoogleAuthController::SESSION_KEY);

        return Inertia::render('Auth/Apply', [
            'googleEnabled' => GoogleAuthController::enabled(),
            // Identité Google déjà vérifiée : nom et email préremplis, pas de mot de passe à choisir.
            'google' => $google ? collect($google)->only(['email', 'first_name', 'last_name']) : null,
            'countries' => Country::forDisplay(Country::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Champ piège invisible : un robot le remplit, un humain non.
        if (filled($request->input('website'))) {
            return redirect()->route('membership.sent');
        }

        $google = $request->session()->get(GoogleAuthController::SESSION_KEY);
        if ($google) {
            // L'email vient de Google (vérifié) : on ne tient pas compte de celui envoyé par le formulaire.
            $request->merge(['email' => $google['email']]);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => $google ? ['exclude'] : ['required', 'confirmed', Password::defaults()],
            // Format international E.164 : « + », indicatif du pays, puis le numéro (8 à 15 chiffres au total).
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'country_id' => ['required', Rule::exists('countries', 'id')->where('is_active', true)],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:150'],
            'date_of_birth' => ['nullable', 'date', 'before:-16 years', 'after:1900-01-01'],
            'consent' => ['accepted'],
        ], [
            'phone.regex' => 'Saisissez un numéro international valide : choisissez l\'indicatif du pays, puis votre numéro.',
            'consent.accepted' => 'Cochez cette case pour envoyer votre demande.',
            'country_id.required' => 'Choisissez votre pays de résidence.',
            'country_id.exists' => 'Choisissez votre pays de résidence dans la liste.',
            'date_of_birth.before' => 'Il faut avoir au moins 16 ans pour faire une demande.',
            'password.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
        ], [
            'first_name' => 'prénom',
            'last_name' => 'nom',
            'phone' => 'téléphone',
            'city' => 'ville',
            'date_of_birth' => 'date de naissance',
        ]);

        // Les essais refusés par la validation ne comptent pas : seules les demandes complètes sont limitées.
        $limiterKey = 'membership-application:'.$request->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Trop de demandes ont été envoyées depuis cette connexion. Réessayez dans :minutes minutes.', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($limiterKey) / 60),
                ]),
            ]);
        }
        RateLimiter::hit($limiterKey, 3600);

        $email = Str::lower(trim($data['email']));

        // Même réponse dans tous les cas : la page ne révèle pas si l'adresse a déjà un compte.
        $request->session()->forget(GoogleAuthController::SESSION_KEY);

        if ($user = User::where('email', $email)->first()) {
            $this->safely(fn () => $user->notifyNow(new ExistingAccountNotice));

            return redirect()->route('membership.sent');
        }

        $alreadyPending = MembershipApplication::where('email', $email)
            ->where('status', MembershipApplication::STATUS_PENDING)
            ->exists();

        if (! $alreadyPending) {
            $application = MembershipApplication::create([
                ...collect($data)->except(['password', 'password_confirmation', 'consent'])->all(),
                'email' => $email,
                'password' => $google ? null : Hash::make($data['password']),
                'google_id' => $google['google_id'] ?? null,
                'country' => Country::find($data['country_id'])->name,
                'status' => MembershipApplication::STATUS_PENDING,
                'ip_address' => $request->ip(),
                'locale' => app()->getLocale(),
            ]);

            $this->safely(fn () => Notification::route('mail', $email)->notifyNow(new ApplicationReceived($application)));
            $this->notifyAdmins($application);
        }

        return redirect()->route('membership.sent');
    }

    public function sent(): Response
    {
        return Inertia::render('Auth/ApplySent');
    }

    /** Liste des demandes pour les administrateurs. */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('manage users'), 403);

        $status = $request->string('status')->toString();
        $status = array_key_exists($status, MembershipApplication::STATUS_LABELS) ? $status : MembershipApplication::STATUS_PENDING;

        $applications = MembershipApplication::query()
            ->with(['reviewer:id,name', 'user.memberProfile:id,user_id'])
            ->where('status', $status)
            ->latest()
            ->get()
            ->map(fn (MembershipApplication $a) => [
                'id' => $a->id,
                'name' => $a->fullName(),
                'email' => $a->email,
                'phone' => $a->phone,
                'country' => $a->country,
                'city' => $a->city,
                'address' => $a->address,
                'profession' => $a->profession,
                'date_of_birth' => $a->date_of_birth?->format('d/m/Y'),
                'family_link' => $a->familyLinkLabel(),
                'sponsor_name' => $a->sponsor_name,
                'message' => $a->message,
                'status' => $a->status,
                'status_label' => MembershipApplication::STATUS_LABELS[$a->status] ?? $a->status,
                'decision_note' => $a->decision_note,
                'reviewer' => $a->reviewer?->name,
                'reviewed_at' => $a->reviewed_at?->timezone('Europe/Paris')->format('d/m/Y à H:i'),
                'created_at' => $a->created_at->timezone('Europe/Paris')->format('d/m/Y à H:i'),
                'member_profile_id' => $a->user?->memberProfile?->id,
            ]);

        $counts = MembershipApplication::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Admin/MembershipApplications', [
            'applications' => $applications,
            'status' => $status,
            'tabs' => collect(MembershipApplication::STATUS_LABELS)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'count' => (int) ($counts[$key] ?? 0),
            ])->values(),
        ]);
    }

    /**
     * Accepter crée le compte (utilisateur + profil membre actif, rôle « member »)
     * avec le mot de passe choisi par le candidat. Refuser ne crée rien.
     */
    public function decide(Request $request, MembershipApplication $membershipApplication): RedirectResponse
    {
        abort_unless($request->user()->can('manage users'), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in([MembershipApplication::STATUS_ACCEPTED, MembershipApplication::STATUS_REFUSED])],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $accepted = $data['decision'] === MembershipApplication::STATUS_ACCEPTED;

        $result = DB::transaction(function () use ($membershipApplication, $data, $accepted, $request) {
            $application = MembershipApplication::whereKey($membershipApplication->id)->lockForUpdate()->first();

            if ($application->status !== MembershipApplication::STATUS_PENDING) {
                return 'already';
            }

            $user = null;
            if ($accepted) {
                if (User::where('email', $application->email)->exists()) {
                    return 'email_taken';
                }
                if (! $application->password && ! $application->google_id) {
                    return 'no_password';
                }

                $user = new User;
                $user->forceFill([
                    'name' => $application->fullName(),
                    'email' => $application->email,
                    // Déjà haché lors de la demande ; demande faite avec Google : mot de passe aléatoire,
                    // la personne se connecte avec Google (ou choisit un mot de passe via « Mot de passe oublié »).
                    'password' => $application->password ?: Hash::make(Str::random(64)),
                    'google_id' => $application->google_id,
                    'email_verified_at' => now(),
                    'locale' => $application->locale,
                ])->save();
                $user->assignRole('member');

                MemberProfile::create([
                    'user_id' => $user->id,
                    'country_id' => $application->country_id,
                    'phone' => $application->phone,
                    'city' => $application->city,
                    'address' => $application->address,
                    'profession' => $application->profession,
                    'date_of_birth' => $application->date_of_birth,
                    'bio' => $application->message,
                    'member_since' => now()->toDateString(),
                    'status' => MemberStatus::Active->value,
                    'notes' => 'Admis via la demande d\'adhésion n° '.$application->id.'.',
                ]);
            }

            $application->forceFill([
                'status' => $data['decision'],
                'decision_note' => $data['decision_note'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'user_id' => $user?->id,
                'password' => null, // le mot de passe haché n'est plus conservé dans la demande
            ])->save();

            activity('administration')
                ->causedBy($request->user())
                ->performedOn($application)
                ->withProperties(['decision' => $data['decision'], 'user_id' => $user?->id])
                ->log($accepted ? 'Demande d\'adhésion acceptée, compte créé' : 'Demande d\'adhésion refusée');

            return $application;
        });

        if (! $result instanceof MembershipApplication) {
            return back()->with('error', match ($result) {
                'already' => 'Cette demande a déjà été traitée.',
                'email_taken' => 'Un compte existe déjà avec cette adresse email : aucun compte n\'a été créé.',
                default => 'Le mot de passe du candidat n\'est plus disponible : aucun compte n\'a été créé.',
            });
        }

        $sent = $this->safely(fn () => Notification::route('mail', $result->email)
            ->notifyNow((new ApplicationDecision($result))->locale($result->locale ?: 'fr')));

        $done = $accepted
            ? __('Demande acceptée : le compte de :name est créé.', ['name' => $result->fullName()])
            : __("Demande de :name refusée. Aucun compte n'a été créé.", ['name' => $result->fullName()]);

        return back()->with(
            $sent ? 'success' : 'error',
            $sent ? $done.' '.__("Un email l'a prévenu(e).") : $done.' '.__("L'email n'a pas pu être envoyé : prévenez la personne directement.")
        );
    }

    private function notifyAdmins(MembershipApplication $application): void
    {
        try {
            $admins = User::permission('manage users')->pluck('email');
        } catch (Throwable) {
            $admins = collect(); // permission absente (base non initialisée) : on garde l'adresse de secours
        }

        $recipients = $admins
            ->push(config('mail.admin_address'))
            ->filter(fn ($email) => $email && filter_var($email, FILTER_VALIDATE_EMAIL) && ! str_ends_with($email, '.test'))
            ->unique()
            ->values();

        foreach ($recipients as $email) {
            $this->safely(fn () => Notification::route('mail', $email)->notifyNow((new NewApplicationForAdmin($application))->locale('fr')));
        }
    }

    /** Un email qui échoue ne doit jamais bloquer le parcours : on l'inscrit dans le journal. */
    private function safely(callable $send): bool
    {
        try {
            $send();

            return true;
        } catch (Throwable $e) {
            Log::error('Envoi d\'email impossible (adhésion) : '.$e->getMessage());

            return false;
        }
    }
}
