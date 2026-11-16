<?php

namespace App\Support\Recette;

use App\Support\MemberRoleHelper;
use Spatie\Permission\Models\Role;

/**
 * Rôles de l'application et actions permises, lus en direct dans la base (Spatie),
 * présentés en français simple pour la page de recette.
 */
class RolesOverview
{
    /** Ordre d'affichage : du plus large au plus restreint. */
    private const ORDER = [
        'super_admin', 'president', 'vice_president', 'treasurer', 'secretary',
        'investment_committee', 'solidarity_committee', 'academy_manager', 'member', 'guest',
    ];

    private const MISSIONS = [
        'super_admin' => 'Gère toute l\'application : accès complet à tous les modules et réglages.',
        'president' => 'Pilote le fonds : membres, décisions sur les projets, votes, documents et administration.',
        'vice_president' => 'Seconde le président pour la gestion des membres.',
        'treasurer' => 'Tient les comptes : cotisations, paiements, mouvements et répartition du fonds, financement des projets.',
        'secretary' => 'Tient les registres : fiches des membres, documents, suivi des cotisations et des votes.',
        'investment_committee' => 'Étudie les projets d\'investissement : examen, avis, approbation ou rejet, documents des projets.',
        'solidarity_committee' => 'Comité solidarité : consultation et participation aux votes.',
        'academy_manager' => 'Responsable Academy : consultation et participation aux votes.',
        'member' => 'Membre de la famille : paie ses cotisations, propose des projets et vote.',
        'guest' => 'Observateur : voit uniquement le tableau de bord.',
    ];

    /** Règles de l'application qui dépendent du rôle lui-même, en plus des permissions. */
    private const EXTRAS = [
        'super_admin' => ['Garde toujours toutes les permissions, même si on modifie les rôles.', 'Peut télécharger les documents de tous les projets.'],
        'president' => ['Peut télécharger les documents de tous les projets.'],
        'treasurer' => ['Peut télécharger les documents de tous les projets.'],
        'secretary' => ['Peut télécharger les documents de tous les projets.'],
        'investment_committee' => ['Peut télécharger les documents de tous les projets.'],
    ];

    /** Module => [permission => action en clair]. */
    private const ACTIONS = [
        'Tableau de bord' => [
            'view dashboard' => 'Voir le tableau de bord',
        ],
        'Membres' => [
            'view members' => 'Voir la liste et les fiches des membres',
            'create members' => 'Ajouter un membre',
            'edit members' => 'Modifier la fiche d\'un membre',
            'suspend members' => 'Suspendre ou réactiver un membre',
            'delete members' => 'Supprimer un membre',
            'export members' => 'Exporter la liste des membres',
        ],
        'Cotisations' => [
            'view own contribution payments' => 'Voir ses propres cotisations',
            'submit contribution payments' => 'Déclarer un paiement de cotisation',
            'view contributions' => 'Voir le suivi des cotisations',
            'view all contribution payments' => 'Voir les paiements de tous les membres',
            'create contributions' => 'Enregistrer une cotisation',
            'edit contributions' => 'Modifier une cotisation',
            'delete contributions' => 'Supprimer une cotisation',
            'validate contribution payments' => 'Valider un paiement',
            'reject contribution payments' => 'Refuser un paiement',
            'manage contribution plans' => 'Gérer les barèmes de cotisation',
        ],
        'Fonds familial' => [
            'view funds' => 'Voir le fonds familial et ses soldes',
            'view fund movements' => 'Voir les mouvements du fonds',
            'create fund movements' => 'Enregistrer un mouvement',
            'edit fund movements' => 'Modifier un mouvement',
            'delete fund movements' => 'Supprimer un mouvement',
            'transfer funds' => 'Faire un virement entre comptes du fonds',
            'manage fund accounts' => 'Gérer les comptes du fonds',
            'manage allocation rules' => 'Gérer les règles de répartition',
        ],
        'Projets' => [
            'view projects' => 'Voir les projets (les siens et ceux ouverts)',
            'view all projects' => 'Voir tous les projets',
            'create projects' => 'Proposer un projet',
            'edit own projects' => 'Modifier ses propres projets',
            'edit all projects' => 'Modifier tous les projets',
            'delete projects' => 'Supprimer un projet',
            'submit projects' => 'Soumettre son projet à l\'examen',
            'review projects' => 'Examiner un projet et donner un avis',
            'approve projects' => 'Approuver un projet',
            'reject projects' => 'Rejeter un projet',
            'submit projects to vote' => 'Mettre un projet au vote',
            'fund projects' => 'Financer un projet',
            'manage project documents' => 'Gérer les documents de tous les projets',
        ],
        'Votes' => [
            'view votes' => 'Voir les votes ouverts',
            'view all votes' => 'Voir tous les votes et leurs résultats',
            'cast votes' => 'Voter',
            'create votes' => 'Créer un vote',
            'manage votes' => 'Gérer les votes',
            'close votes' => 'Clôturer un vote',
        ],
        'Documents' => [
            'view document library' => 'Consulter la bibliothèque de documents',
            'manage documents' => 'Ajouter et modifier des documents',
            'publish documents' => 'Publier des documents',
            'manage document access' => 'Gérer qui peut voir chaque document',
            'view document audit' => 'Voir l\'historique des consultations',
        ],
        'Administration' => [
            'access administration' => 'Accéder à l\'administration',
            'manage users' => 'Gérer les comptes et les demandes d\'adhésion',
            'manage roles' => 'Gérer les rôles',
            'manage permissions' => 'Gérer les permissions',
            'manage settings' => 'Gérer les réglages de l\'application',
            'manage document categories' => 'Gérer les catégories de documents',
            'manage zones' => 'Gérer les zones et leurs trésoriers',
            'manage transaction types' => 'Gérer les types de transactions et le nombre de validations',
            'manage project types' => 'Gérer les types de projets et leurs listes de critères',
            'approve transactions' => 'Valider les transactions (décaissements, remboursements, sorties d\'argent)',
            'view reports' => 'Consulter les rapports, relevés et exports',
            'manage reports' => 'Partager les rapports et organiser la vérification annuelle',
            'manage withdrawals' => 'Gérer les départs de membres et leurs remboursements',
        ],
    ];

    public static function all(): array
    {
        $known = collect(self::ACTIONS)->flatMap(fn ($actions) => array_keys($actions))->all();

        return Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->get()
            ->sortBy(fn (Role $r) => array_search($r->name, self::ORDER, true) === false ? 999 : array_search($r->name, self::ORDER, true))
            ->map(function (Role $role) use ($known) {
                $granted = $role->name === 'super_admin'
                    ? $known
                    : $role->permissions->pluck('name')->all();

                $groups = collect(self::ACTIONS)
                    ->map(fn ($actions, $module) => [
                        'module' => $module,
                        'actions' => collect($actions)->filter(fn ($label, $perm) => in_array($perm, $granted, true))->values()->all(),
                    ])
                    ->filter(fn ($g) => $g['actions'])
                    ->values();

                // Permission créée depuis l'administration et pas encore décrite ici : on l'affiche telle quelle.
                $other = array_values(array_diff($granted, $known));
                if ($other) {
                    $groups->push(['module' => 'Autres', 'actions' => $other]);
                }

                return [
                    'name' => $role->name,
                    'label' => MemberRoleHelper::label($role->name),
                    'mission' => self::MISSIONS[$role->name] ?? '',
                    'users_count' => $role->users_count,
                    'actions_count' => $groups->sum(fn ($g) => count($g['actions'])),
                    'groups' => $groups->all(),
                    'extras' => self::EXTRAS[$role->name] ?? [],
                ];
            })
            ->values()
            ->all();
    }

    /** Tous les textes affichés, pour vérifier qu'ils ont une traduction anglaise. */
    public static function texts(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(self::ACTIONS),
            ...array_values(array_map('array_values', self::ACTIONS)),
            ...[array_values(self::MISSIONS)],
            ...array_values(self::EXTRAS),
        )));
    }
}
