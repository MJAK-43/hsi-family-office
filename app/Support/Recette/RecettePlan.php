<?php

namespace App\Support\Recette;

/**
 * Plan de recette de l'application (une journée = un module).
 * Les clés de tâches (« 2.1 »…) sont stables : les résultats enregistrés y font référence.
 * Pour changer une date, modifier « date » ; ne pas renuméroter une tâche déjà testée.
 */
class RecettePlan
{
    public static function days(): array
    {
        return [
            [
                'number' => 1,
                'date' => '2026-11-09',
                'module' => 'Connexion et adhésion',
                'intro' => "Testez l'entrée dans l'application : la demande d'adhésion, la connexion et votre profil. Utilisez une vraie adresse e-mail que vous consultez.",
                'tasks' => [
                    ['key' => '1.1', 'title' => "Faire une demande d'adhésion", 'role' => 'Visiteur (non connecté)',
                        'steps' => ["Ouvrir la page de connexion, cliquer sur « Demander mon adhésion ».", 'Remplir tout le formulaire : choisir un pays, vérifier que l’indicatif téléphonique se met à jour, saisir un mot de passe de 12 caractères minimum.', 'Envoyer la demande.'],
                        'expected' => "Une page de confirmation s'affiche et un e-mail « Votre demande d'adhésion a bien été reçue » arrive (vérifier aussi les spams)."],
                    ['key' => '1.2', 'title' => 'Erreurs du formulaire d’adhésion', 'role' => 'Visiteur (non connecté)',
                        'steps' => ['Envoyer le formulaire avec des champs vides, un e-mail invalide, deux mots de passe différents, un numéro de téléphone trop court.'],
                        'expected' => 'Chaque erreur est signalée clairement sous le bon champ, rien n’est envoyé.'],
                    ['key' => '1.3', 'title' => 'Se connecter', 'role' => 'Membre',
                        'steps' => ['Se connecter avec son compte.', 'Essayer aussi un mauvais mot de passe.'],
                        'expected' => 'Avec le bon mot de passe, le tableau de bord s’ouvre. Avec un mauvais, un message d’erreur clair s’affiche.'],
                    ['key' => '1.4', 'title' => 'Mot de passe oublié', 'role' => 'Membre',
                        'steps' => ['Se déconnecter, cliquer sur « Mot de passe oublié ».', 'Saisir son e-mail, ouvrir l’e-mail reçu, choisir un nouveau mot de passe.', 'Se reconnecter avec le nouveau mot de passe.'],
                        'expected' => 'L’e-mail arrive en quelques minutes, le lien fonctionne une seule fois, la connexion marche avec le nouveau mot de passe.'],
                    ['key' => '1.5', 'title' => 'Compléter son profil', 'role' => 'Membre',
                        'steps' => ['Menu en haut à droite → « Mon profil ».', 'Modifier téléphone, pays, ville, profession, date de naissance, biographie. Enregistrer.'],
                        'expected' => 'Un message confirme l’enregistrement et les informations restent affichées après rechargement de la page.'],
                    ['key' => '1.6', 'title' => 'Photo de profil', 'role' => 'Membre',
                        'steps' => ['Sur « Mon profil », cliquer sur l’appareil photo et choisir une photo.', 'Essayer ensuite de la retirer.'],
                        'expected' => 'La photo apparaît dans le profil et en haut à droite. Une image trop petite ou un fichier non image est refusé avec un message.'],
                    ['key' => '1.7', 'title' => 'Changer de langue (FR / EN)', 'role' => 'Membre',
                        'steps' => ['Cliquer sur EN en haut à droite, parcourir quelques pages, puis revenir à FR.'],
                        'expected' => 'Toute l’interface passe en anglais puis revient en français. Le choix est conservé à la connexion suivante.'],
                ],
            ],
            [
                'number' => 2,
                'date' => '2026-11-10',
                'module' => 'Membres',
                'intro' => "Testez la gestion des membres : les demandes d'adhésion et l'annuaire. Les tâches 2.1 à 2.3 et 2.6 demandent un compte administrateur.",
                'tasks' => [
                    ['key' => '2.1', 'title' => "Accepter une demande d'adhésion", 'role' => 'Administrateur',
                        'steps' => ['Menu « Adhésions », ouvrir une demande en attente.', 'Cliquer sur « Accepter », ajouter éventuellement un message, confirmer.'],
                        'expected' => 'Le compte est créé, la personne reçoit l’e-mail de bienvenue et peut se connecter avec le mot de passe choisi lors de sa demande.'],
                    ['key' => '2.2', 'title' => "Refuser une demande d'adhésion", 'role' => 'Administrateur',
                        'steps' => ['Menu « Adhésions », ouvrir une autre demande, cliquer sur « Refuser », confirmer.'],
                        'expected' => 'Aucun compte n’est créé, la personne reçoit un e-mail de réponse, la demande passe dans l’onglet des refusées.'],
                    ['key' => '2.3', 'title' => 'Ajouter un membre manuellement', 'role' => 'Administrateur',
                        'steps' => ['Menu « Membres » → « Ajouter un membre ».', 'Remplir la fiche (nom, e-mail, rôle, pays, branche familiale) et enregistrer.'],
                        'expected' => 'Le membre apparaît dans l’annuaire avec les bonnes informations.'],
                    ['key' => '2.4', 'title' => 'Annuaire des membres', 'role' => 'Membre',
                        'steps' => ['Menu « Membres ».', 'Utiliser la recherche, les filtres (pays, branche, rôle, statut) et la pagination.'],
                        'expected' => 'Les cartes des membres actifs s’affichent avec photo, âge, pays et profession. Les filtres donnent des résultats cohérents.'],
                    ['key' => '2.5', 'title' => 'Fiche d’un membre', 'role' => 'Membre',
                        'steps' => ['Cliquer sur « Voir » sur une carte de membre.'],
                        'expected' => 'La fiche affiche les informations du membre, sans erreur.'],
                    ['key' => '2.6', 'title' => 'Modifier, suspendre et réactiver un membre', 'role' => 'Administrateur',
                        'steps' => ['Ouvrir la fiche d’un membre de test, cliquer sur « Modifier », changer une information, enregistrer.', 'Suspendre ce membre, essayer de se connecter avec son compte, puis le réactiver.'],
                        'expected' => 'La modification est enregistrée. Un membre suspendu ne peut plus accéder à l’application ; réactivé, il y accède de nouveau.'],
                ],
            ],
            [
                'number' => 3,
                'date' => '2026-11-11',
                'module' => 'Projets',
                'intro' => "Testez le parcours d'un projet jusqu'à la décision du comité. Gardez un projet approuvé par le comité : il servira au vote de demain.",
                'tasks' => [
                    ['key' => '3.1', 'title' => 'Déposer un projet', 'role' => 'Membre',
                        'steps' => ['Menu « Projets » → « Déposer un projet ».', 'Remplir le titre, le secteur, le pays, le budget total, l’apport personnel et le montant demandé à HSI. Enregistrer.'],
                        'expected' => 'Le projet est créé en brouillon et sa fiche s’affiche.'],
                    ['key' => '3.2', 'title' => 'Règle 50/50', 'role' => 'Membre',
                        'steps' => ['Créer ou modifier un projet avec un apport personnel inférieur à 50 % du budget, ou une demande à HSI supérieure à 50 %.'],
                        'expected' => 'L’enregistrement est refusé avec un message qui explique la règle 50/50.'],
                    ['key' => '3.3', 'title' => 'Ajouter des documents au projet', 'role' => 'Membre',
                        'steps' => ['Sur la fiche du projet, ajouter un business plan (PDF) puis un devis.', 'Télécharger un document, puis en supprimer un.'],
                        'expected' => 'Les documents apparaissent, se téléchargent et se suppriment correctement.'],
                    ['key' => '3.4', 'title' => 'Soumettre le projet pour analyse', 'role' => 'Membre',
                        'steps' => ['Sur la fiche du projet, cliquer sur « Soumettre » et confirmer.'],
                        'expected' => 'Le statut passe à « Soumis » et le projet ne peut plus être modifié par son porteur.'],
                    ['key' => '3.5', 'title' => 'Évaluer le projet', 'role' => 'Comité d’investissement',
                        'steps' => ['Ouvrir le projet soumis, cliquer sur « Évaluer le projet ».', 'Noter chaque critère de 0 à 20, ajouter une recommandation, enregistrer.'],
                        'expected' => 'L’évaluation et le score moyen apparaissent sur la fiche du projet.'],
                    ['key' => '3.6', 'title' => 'Décision du comité', 'role' => 'Comité d’investissement',
                        'steps' => ['Approuver un projet.', 'Rejeter un autre projet en indiquant un motif.'],
                        'expected' => 'Le premier passe à « Approuvé comité », le second à « Rejeté comité » avec le motif visible.'],
                    ['key' => '3.7', 'title' => 'Liste et filtres des projets', 'role' => 'Membre',
                        'steps' => ['Menu « Projets » : utiliser la recherche et les filtres (statut, secteur, pays).'],
                        'expected' => 'Les projets s’affichent avec leur statut, les filtres fonctionnent.'],
                ],
            ],
            [
                'number' => 4,
                'date' => '2026-11-12',
                'module' => 'Votes',
                'intro' => "Testez le vote familial sur le projet approuvé hier par le comité. Il faut plusieurs membres votants : coordonnez-vous dans la journée.",
                'tasks' => [
                    ['key' => '4.1', 'title' => 'Envoyer le projet au vote', 'role' => 'Président',
                        'steps' => ['Ouvrir le projet « Approuvé comité », cliquer sur « Envoyer au vote » et confirmer.'],
                        'expected' => 'Un vote familial est créé et ouvert ; il apparaît dans le menu « Votes ».'],
                    ['key' => '4.2', 'title' => 'Créer un vote libre', 'role' => 'Président',
                        'steps' => ['Menu « Votes » → « Créer un vote ».', 'Choisir le type, la règle de majorité, le quorum, la date de clôture, avec ou sans abstention. Créer.'],
                        'expected' => 'Le vote est créé avec les bons paramètres.'],
                    ['key' => '4.3', 'title' => 'Déclarer un conflit d’intérêts', 'role' => 'Membre',
                        'steps' => ['Sur un vote pas encore ouvert, cliquer sur « Déclarer mon conflit d’intérêts ».'],
                        'expected' => 'Le conflit est enregistré et visible pour le président.'],
                    ['key' => '4.4', 'title' => 'Voter', 'role' => 'Membre',
                        'steps' => ['Ouvrir un vote ouvert, choisir une option, cliquer sur « Enregistrer mon vote ».', 'Essayer de voter une deuxième fois.'],
                        'expected' => 'Le vote est enregistré (« Vous avez voté »). Un second vote est impossible. La participation augmente.'],
                    ['key' => '4.5', 'title' => 'Clôturer le vote et lire le résultat', 'role' => 'Président',
                        'steps' => ['Une fois que plusieurs membres ont voté, cliquer sur « Clôturer » et confirmer.'],
                        'expected' => 'Le résultat (approuvé, rejeté, égalité ou quorum non atteint) s’affiche. Si le vote du projet est approuvé, le projet passe à « Approuvé famille ».'],
                    ['key' => '4.6', 'title' => 'Annuler un vote', 'role' => 'Président',
                        'steps' => ['Sur le vote libre créé en 4.2, cliquer sur « Annuler » et confirmer.'],
                        'expected' => 'Le vote passe à « Annulé » et on ne peut plus y voter.'],
                ],
            ],
            [
                'number' => 5,
                'date' => '2026-11-13',
                'module' => 'Documents, administration et contrôle général',
                'intro' => "Testez la bibliothèque de documents et l'administration, puis faites un tour complet de l'application sur téléphone et en anglais.",
                'tasks' => [
                    ['key' => '5.1', 'title' => 'Créer et publier un document', 'role' => 'Secrétaire ou administrateur',
                        'steps' => ['Menu « Documents » → « Nouveau document ». Choisir une catégorie, joindre un fichier, créer.', 'Soumettre à validation, puis publier.'],
                        'expected' => 'Le document passe de « Brouillon » à « À valider » puis « Publié ».'],
                    ['key' => '5.2', 'title' => 'Versions et archivage d’un document', 'role' => 'Secrétaire ou administrateur',
                        'steps' => ['Ajouter une nouvelle version au document.', 'L’archiver puis le restaurer.'],
                        'expected' => 'L’historique des versions s’affiche ; l’archivage et la restauration fonctionnent.'],
                    ['key' => '5.3', 'title' => 'Droits d’accès d’un document', 'role' => 'Secrétaire ou administrateur',
                        'steps' => ['Sur un document, limiter l’accès à certains rôles, enregistrer.', 'Se connecter avec un compte qui n’a pas ce rôle.'],
                        'expected' => 'Le document n’est ni visible ni téléchargeable par un membre non autorisé.'],
                    ['key' => '5.4', 'title' => 'Consulter et télécharger', 'role' => 'Membre',
                        'steps' => ['Menu « Documents » : rechercher, filtrer par catégorie, télécharger un document publié.'],
                        'expected' => 'Le téléchargement fonctionne et le fichier est intact.'],
                    ['key' => '5.5', 'title' => 'Rôles et permissions', 'role' => 'Administrateur',
                        'steps' => ['Menu « Administration » : changer le rôle d’un utilisateur de test, modifier une permission d’un rôle.', 'Se connecter avec ce compte pour vérifier.'],
                        'expected' => 'Les menus et actions disponibles changent selon le nouveau rôle.'],
                    ['key' => '5.6', 'title' => 'Paramètres et journal d’audit', 'role' => 'Administrateur',
                        'steps' => ['Modifier un paramètre de l’application, enregistrer.', 'Consulter le journal d’audit.'],
                        'expected' => 'Le paramètre est enregistré ; les actions récentes apparaissent dans le journal.'],
                    ['key' => '5.7', 'title' => 'Tour complet sur téléphone', 'role' => 'Membre',
                        'steps' => ['Sur un téléphone, ouvrir chaque menu de l’application.'],
                        'expected' => 'Tout est lisible, rien ne dépasse de l’écran, le menu s’ouvre et se ferme correctement.'],
                    ['key' => '5.8', 'title' => 'Tour complet en anglais', 'role' => 'Membre',
                        'steps' => ['Passer en EN et ouvrir chaque menu.'],
                        'expected' => 'Tous les textes de l’interface sont en anglais (le contenu saisi par les membres reste dans sa langue).'],
                ],
            ],
            [
                'number' => 6,
                'date' => '2026-11-14',
                'module' => 'Fonds familial',
                'intro' => "Testez la gestion du fonds et terminez le parcours du projet : financement du projet approuvé jeudi par la famille.",
                'tasks' => [
                    ['key' => '6.1', 'title' => 'Vue d’ensemble du fonds', 'role' => 'Trésorier',
                        'steps' => ['Menu « Fonds familial » : consulter les poches, le graphique de répartition et l’évolution.'],
                        'expected' => 'Les soldes et graphiques s’affichent sans erreur.'],
                    ['key' => '6.2', 'title' => 'Entrée manuelle dans « Projets familiaux »', 'role' => 'Trésorier',
                        'steps' => ['« Ajouter un mouvement » : poche « Projets familiaux », direction Crédit, montant supérieur au montant demandé par le projet voté jeudi.'],
                        'expected' => 'Le mouvement apparaît et le solde de la poche augmente du montant saisi.'],
                    ['key' => '6.3', 'title' => 'Sortie manuelle et solde insuffisant', 'role' => 'Trésorier',
                        'steps' => ['Faire une sortie (Débit) d’un petit montant.', 'Essayer une sortie supérieure au solde de la poche.'],
                        'expected' => 'La petite sortie est enregistrée ; la sortie trop élevée est refusée avec un message.'],
                    ['key' => '6.4', 'title' => 'Transfert entre poches', 'role' => 'Trésorier',
                        'steps' => ['« Faire un transfert » : choisir une poche source, une poche destination, un montant et un motif.'],
                        'expected' => 'Le solde de la source baisse et celui de la destination augmente du même montant.'],
                    ['key' => '6.5', 'title' => 'Annuler un mouvement', 'role' => 'Trésorier',
                        'steps' => ['Dans les mouvements, annuler le mouvement de 6.3 en indiquant un motif.'],
                        'expected' => 'Le mouvement passe à « Annulé » et le solde est rétabli.'],
                    ['key' => '6.6', 'title' => 'Règles de répartition', 'role' => 'Trésorier',
                        'steps' => ['Consulter les règles d’allocation, en créer une nouvelle avec un total différent de 100 %, puis une à 100 %.'],
                        'expected' => 'Une règle dont le total n’est pas 100 % est refusée ; celle à 100 % est créée.'],
                    ['key' => '6.7', 'title' => 'Financer le projet voté', 'role' => 'Trésorier ou président',
                        'steps' => ['Ouvrir le projet « Approuvé famille » (vote de jeudi), cliquer sur « Financer » et confirmer.'],
                        'expected' => 'Le projet passe à « Financé », la poche « Projets familiaux » est débitée du montant demandé.'],
                ],
            ],
            [
                'number' => 7,
                'date' => '2026-11-15',
                'module' => 'Cotisations',
                'intro' => "Dernier module : les cotisations, de la déclaration du membre jusqu'à la répartition automatique dans le fonds.",
                'tasks' => [
                    ['key' => '7.1', 'title' => 'Plans de cotisation', 'role' => 'Trésorier',
                        'steps' => ['« Plans de cotisation » : créer un plan (montant, fréquence, pays), le modifier, le désactiver puis le réactiver.'],
                        'expected' => 'Toutes les opérations fonctionnent et le plan s’affiche avec les bonnes valeurs.'],
                    ['key' => '7.2', 'title' => 'Déclarer une cotisation', 'role' => 'Membre',
                        'steps' => ['« Soumettre une cotisation » : montant, méthode de paiement, date, et joindre une preuve (photo ou PDF).'],
                        'expected' => 'La cotisation apparaît « En attente » avec la preuve consultable.'],
                    ['key' => '7.3', 'title' => 'Valider une cotisation', 'role' => 'Trésorier',
                        'steps' => ['Dans la liste des cotisations, valider celle déclarée en 7.2.'],
                        'expected' => 'Elle passe à « Validée » et le membre en est informé.'],
                    ['key' => '7.4', 'title' => 'Répartition automatique dans le fonds', 'role' => 'Trésorier',
                        'steps' => ['Après la validation de 7.3, ouvrir « Fonds familial » et les mouvements.'],
                        'expected' => 'Le montant validé est réparti entre les poches selon la règle par défaut (40 % investissement, 30 % projets, 20 % solidarité, 10 % éducation).'],
                    ['key' => '7.5', 'title' => 'Refuser une cotisation', 'role' => 'Trésorier',
                        'steps' => ['Refuser une autre cotisation en indiquant un motif (5 caractères minimum).'],
                        'expected' => 'Elle passe à « Refusée » avec le motif visible ; rien n’est versé au fonds.'],
                    ['key' => '7.6', 'title' => 'Échéances et retards', 'role' => 'Trésorier',
                        'steps' => ['Cliquer sur « Actualiser les échéances ».', 'Exonérer une échéance en indiquant un motif.'],
                        'expected' => 'Les échéances et retards s’affichent ; l’échéance exonérée disparaît des impayés.'],
                    ['key' => '7.7', 'title' => 'Filtres de la liste des cotisations', 'role' => 'Trésorier',
                        'steps' => ['Utiliser la recherche et les filtres (statut, méthode, mois, année, pays, branche) et la pagination.'],
                        'expected' => 'Les résultats correspondent aux filtres choisis.'],
                ],
            ],
        ];
    }

    /** Plan dans la langue demandée (anglais : textes de RecettePlanEnglish, français en secours). */
    public static function localized(string $locale): array
    {
        if ($locale !== 'en') {
            return self::days();
        }

        $days = RecettePlanEnglish::days();
        $tasks = RecettePlanEnglish::tasks();

        return array_map(function (array $day) use ($days, $tasks) {
            $day = array_merge($day, $days[$day['number']] ?? []);
            $day['tasks'] = array_map(fn (array $task) => array_merge($task, $tasks[$task['key']] ?? []), $day['tasks']);

            return $day;
        }, self::days());
    }

    /** Toutes les clés de tâches valides. */
    public static function taskKeys(): array
    {
        return collect(self::days())->flatMap(fn ($day) => collect($day['tasks'])->pluck('key'))->all();
    }

    /** Index « clé → [jour, tâche] » pour l'export. */
    public static function taskIndex(): array
    {
        $index = [];
        foreach (self::days() as $day) {
            foreach ($day['tasks'] as $task) {
                $index[$task['key']] = ['day' => $day, 'task' => $task];
            }
        }

        return $index;
    }
}
