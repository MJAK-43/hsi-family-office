<?php

namespace App\Support;

/**
 * Types de projets installés au départ, avec leur liste de critères.
 * Critère : [libellé, explication, bloquant, vérification automatique].
 */
class ProjectTypeDefaults
{
    public const TYPES = [
        [
            'name' => 'Prêt', 'code' => 'loan', 'is_loan' => true, 'requires_personal_contribution' => false,
            'description' => 'Argent prêté par le fonds à un membre pour réaliser un projet, remboursable avec intérêts (section 5 du règlement).',
            'criteria' => [
                ['Les prêts sont ouverts', 'Aucun prêt avant la date d’ouverture prévue par le règlement (janvier 2027).', true, 'loans_open'],
                ['Le demandeur cotise au fonds', 'Section 5.1 : seuls les membres qui contribuent peuvent emprunter.', true, 'contributing_member'],
                ['Au moins 6 mois de cotisation', 'Section 7 : 2 contributions trimestrielles consécutives au minimum.', true, 'seniority'],
                ['Cotisations à jour', 'Aucune échéance de cotisation en retard.', true, 'contributions_up_to_date'],
                ['Aucun prêt en cours', 'Section 5.1 : l’ancien prêt doit être totalement remboursé, intérêts et pénalités compris.', true, 'no_ongoing_loan'],
                ['Aucune pénalité impayée', 'Pénalités de cotisation ou de remboursement.', true, 'no_pending_penalties'],
                ['Montant ≤ 50 % du solde du fonds', 'Section 5.5 : plafond calculé au moment de la demande.', true, 'amount_within_fund_cap'],
                ['Durée de remboursement ≤ 12 mois', 'Section 5.5.', true, 'loan_duration'],
                ['Délai après un refus respecté', 'Section 5.4 : 3 mois pour le même projet, 30 jours pour un autre projet.', true, 'refusal_delay'],
                ['Groupe prévenu 15 à 30 jours avant la présentation', 'Section 5.2.', true, 'notice_period'],
                ['Projet présenté en réunion (appel vidéo)', 'Section 5.3 : nature du projet, montant, plan de remboursement.', true, null],
                ['Plan de remboursement réaliste', 'Le plan proposé est compatible avec la situation du demandeur.', true, null],
                ['Accord écrit prêt à être signé', 'Signé par l’emprunteur et les deux trésoriers avant tout versement.', false, null],
            ],
        ],
        [
            'name' => 'Investissement', 'code' => 'investment', 'is_loan' => false, 'requires_personal_contribution' => true,
            'description' => 'Projet familial cofinancé : l’apport personnel couvre au moins 50 % du budget.',
            'criteria' => [
                ['Le porteur cotise au fonds', null, true, 'contributing_member'],
                ['Cotisations à jour', null, true, 'contributions_up_to_date'],
                ['Apport personnel d’au moins 50 % du budget', null, true, 'personal_contribution'],
                ['Montant ≤ 50 % du solde du fonds', null, true, 'amount_within_fund_cap'],
                ['Délai après un refus respecté', null, true, 'refusal_delay'],
                ['Business plan et prévisions financières fournis', null, true, null],
                ['Risques identifiés et maîtrisés', null, true, null],
                ['Alignement avec les valeurs HSI', null, false, null],
            ],
        ],
    ];

    /** Vérifications automatiques disponibles pour les critères. */
    public const AUTO_CHECKS = [
        'loans_open' => 'Les prêts sont ouverts',
        'contributing_member' => 'Le demandeur cotise au fonds',
        'seniority' => 'Ancienneté de cotisation suffisante',
        'contributions_up_to_date' => 'Cotisations à jour',
        'no_ongoing_loan' => 'Aucun prêt en cours',
        'no_pending_penalties' => 'Aucune pénalité impayée',
        'amount_within_fund_cap' => 'Montant ≤ plafond du solde du fonds',
        'loan_duration' => 'Durée de remboursement ≤ maximum',
        'refusal_delay' => 'Délai après un refus respecté',
        'notice_period' => 'Délai de prévenance respecté',
        'personal_contribution' => 'Apport personnel ≥ 50 % du budget',
    ];
}
