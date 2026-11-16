<?php

namespace App\Support;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Valeurs chiffrées du règlement intérieur du Fonds familial JOKUNG.
 * Elles sont modifiables dans « Paramètres du fonds » (après un vote du groupe à 80 %, section 9).
 */
class FundRules
{

    public const DEFINITIONS = [
        // Informations du fonds : facultatives, à compléter quand elles seront connues.
        'association_name' => ['label' => 'Nom de l’association à but non lucratif', 'type' => 'string', 'default' => '', 'optional' => true],
        'governing_state' => ['label' => 'État américain (lois applicables, enregistrement)', 'type' => 'string', 'default' => '', 'optional' => true],
        'bank_name' => ['label' => 'Banque du compte commun', 'type' => 'string', 'default' => '', 'optional' => true],
        'bank_account_numbers' => ['label' => 'Numéro de routage et de compte', 'type' => 'string', 'default' => '', 'optional' => true],
        'bank_location' => ['label' => 'Ville et État du compte', 'type' => 'string', 'default' => '', 'optional' => true],
        'first_payment_date' => ['label' => 'Date du premier paiement attendu', 'type' => 'date', 'default' => '', 'optional' => true],

        'policy_version' => ['label' => 'Version du règlement intérieur', 'type' => 'string', 'default' => '2026-11', 'description' => 'Changer cette valeur oblige tous les membres à accepter de nouveau le règlement.'],

        'contribution_quarterly_amount' => ['label' => 'Cotisation trimestrielle minimale ($)', 'type' => 'decimal', 'default' => 75, 'min' => 0, 'description' => 'Section 4.1 : montant mensuel × 3 mois.'],
        'contribution_due_day' => ['label' => 'Date limite de cotisation (jour du 1er mois du trimestre)', 'type' => 'integer', 'default' => 15, 'min' => 1, 'max' => 28],
        'contribution_penalty_amount' => ['label' => 'Pénalité de retard de cotisation ($)', 'type' => 'decimal', 'default' => 2, 'min' => 0, 'description' => 'Section 4.3 : ajoutée à chaque période entamée.'],
        'contribution_penalty_start_day' => ['label' => 'Début de la pénalité (jour du mois)', 'type' => 'integer', 'default' => 16, 'min' => 1, 'max' => 28],
        'contribution_penalty_period_days' => ['label' => 'Périodicité de la pénalité (jours)', 'type' => 'integer', 'default' => 7, 'min' => 1, 'max' => 31],
        'contribution_confirmation_days' => ['label' => 'Délai de confirmation d’un paiement par le trésorier (jours ouvrés)', 'type' => 'integer', 'default' => 2, 'min' => 1, 'max' => 30],

        'loans_open_on' => ['label' => 'Ouverture des prêts', 'type' => 'date', 'default' => '2027-01-01', 'description' => 'Section 5 : aucun prêt avant cette date.'],
        'loan_min_contribution_months' => ['label' => 'Ancienneté de cotisation minimale pour emprunter (mois)', 'type' => 'integer', 'default' => 6, 'min' => 0, 'max' => 60],
        'loan_max_balance_percent' => ['label' => 'Plafond d’un prêt (% du solde du fonds)', 'type' => 'decimal', 'default' => 50, 'min' => 1, 'max' => 100],
        'loan_annual_interest_rate' => ['label' => 'Taux d’intérêt annuel (%)', 'type' => 'decimal', 'default' => 4, 'min' => 0, 'max' => 100],
        'loan_max_duration_months' => ['label' => 'Durée maximale de remboursement (mois)', 'type' => 'integer', 'default' => 12, 'min' => 1, 'max' => 120],
        'loan_late_penalty_percent' => ['label' => 'Pénalité de retard de remboursement (% de l’échéance par mois)', 'type' => 'decimal', 'default' => 1, 'min' => 0, 'max' => 100],
        'loan_grace_days' => ['label' => 'Délai de grâce avant le 1er rappel (jours)', 'type' => 'integer', 'default' => 10, 'min' => 0, 'max' => 90],
        'loan_second_reminder_days' => ['label' => '2e rappel, tous les membres informés (jours de retard)', 'type' => 'integer', 'default' => 15, 'min' => 1, 'max' => 180],
        'loan_third_reminder_days' => ['label' => '3e rappel, décision collective (jours de retard)', 'type' => 'integer', 'default' => 30, 'min' => 1, 'max' => 365],

        'project_notice_min_days' => ['label' => 'Prévenir le groupe au moins (jours avant la présentation)', 'type' => 'integer', 'default' => 15, 'min' => 0, 'max' => 180],
        'project_notice_max_days' => ['label' => 'Prévenir le groupe au plus (jours avant la présentation)', 'type' => 'integer', 'default' => 30, 'min' => 0, 'max' => 365],
        'refusal_wait_same_project_months' => ['label' => 'Après un refus : attente pour le même projet (mois)', 'type' => 'integer', 'default' => 3, 'min' => 0, 'max' => 24],
        'refusal_wait_other_project_days' => ['label' => 'Après un refus : attente pour un autre projet (jours)', 'type' => 'integer', 'default' => 30, 'min' => 0, 'max' => 365],

        'vote_approval_percent' => ['label' => 'Seuil d’approbation des votes (% des voix pour et contre)', 'type' => 'decimal', 'default' => 80, 'min' => 50, 'max' => 100, 'description' => 'Les abstentions ne sont pas comptées.'],
        'vote_duration_days' => ['label' => 'Durée d’un vote (jours)', 'type' => 'integer', 'default' => 7, 'min' => 1, 'max' => 60],

        'admission_notice_months' => ['label' => 'Préavis avant une adhésion (mois)', 'type' => 'integer', 'default' => 1, 'min' => 0, 'max' => 12],
        'withdrawal_notice_months' => ['label' => 'Préavis avant un retrait (mois)', 'type' => 'integer', 'default' => 2, 'min' => 0, 'max' => 12],
        'withdrawal_refund_percent' => ['label' => 'Part des cotisations rendue au départ (%)', 'type' => 'decimal', 'default' => 95, 'min' => 0, 'max' => 100],
        'withdrawal_max_refund_months' => ['label' => 'Délai maximal de remboursement au départ (mois)', 'type' => 'integer', 'default' => 12, 'min' => 1, 'max' => 36],
    ];

    /** [code, nom, sens, validations requises, description] */
    public const SYSTEM_TRANSACTION_TYPES = [
        ['contribution', 'Cotisation', 'in', 1, 'Cotisation trimestrielle ou volontaire d’un membre.'],
        ['contribution_penalty', 'Pénalité de retard de cotisation', 'in', 1, 'Pénalité de la section 4.3.'],
        ['loan_disbursement', 'Décaissement de prêt', 'out', 2, 'Versement d’un prêt ou d’un financement de projet approuvé.'],
        ['loan_repayment', 'Remboursement de prêt', 'in', 1, 'Échéance remboursée par un emprunteur.'],
        ['loan_penalty', 'Pénalité de retard de remboursement', 'in', 1, 'Pénalité de la section 5.5.'],
        ['withdrawal_refund', 'Remboursement d’un membre sortant', 'out', 2, 'Section 8 : retrait d’un membre.'],
        ['bank_income', 'Intérêts et revenus bancaires', 'in', 1, 'Revenus du compte commun, conservés dans le fonds.'],
        ['manual_expense', 'Dépense', 'out', 2, 'Toute autre sortie d’argent du compte commun.'],
        ['internal_transfer', 'Virement interne', 'internal', 1, 'Virement entre les comptes du fonds.'],
    ];

    private static ?array $cache = null;

    public static function get(string $key): mixed
    {
        $definition = self::DEFINITIONS[$key] ?? null;
        if (self::$cache === null) {
            self::$cache = Schema::hasTable('application_settings')
                ? ApplicationSetting::whereIn('key', array_keys(self::DEFINITIONS))->pluck('value', 'key')->all()
                : [];
        }
        $value = self::$cache[$key] ?? $definition['default'] ?? null;

        return match ($definition['type'] ?? 'string') {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            default => $value,
        };
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** Règles de validation d'une valeur saisie dans l'écran des paramètres. */
    public static function validationRules(string $key): array
    {
        $definition = self::DEFINITIONS[$key];
        $rules = [($definition['optional'] ?? false) ? 'nullable' : 'required'];
        $rules[] = match ($definition['type']) {
            'integer' => 'integer',
            'decimal' => 'numeric',
            'date' => 'date_format:Y-m-d',
            default => 'string',
        };
        if (isset($definition['min'])) $rules[] = 'min:'.$definition['min'];
        if (isset($definition['max'])) $rules[] = 'max:'.$definition['max'];
        if ($definition['type'] === 'string') $rules[] = ($definition['optional'] ?? false) ? 'max:255' : 'max:60';

        return $rules;
    }

    /** Pour l'écran de paramètres et la page du règlement. */
    public static function all(): array
    {
        return collect(self::DEFINITIONS)->map(fn ($definition, $key) => [
            'key' => $key,
            'label' => $definition['label'],
            'type' => $definition['type'],
            'description' => $definition['description'] ?? null,
            'min' => $definition['min'] ?? null,
            'max' => $definition['max'] ?? null,
            'optional' => $definition['optional'] ?? false,
            'value' => self::get($key),
        ])->values()->all();
    }
}
