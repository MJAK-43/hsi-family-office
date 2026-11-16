<?php

namespace App\Support;

use App\Models\User;

/**
 * Règlement intérieur du Fonds familial JOKUNG (reprise fidèle du document PDF).
 * Les passages entre {accolades} sont des informations encore à compléter dans le document officiel.
 * Blocs : ['p', texte] paragraphe · ['ul', [éléments]] liste · ['table', [en-têtes], [[lignes]]] · ['note', texte] encadré.
 */
class InternalPolicy
{
    public static function sections(string $locale = 'fr'): array
    {
        return $locale === 'en' ? self::english() : self::french();
    }

    /**
     * Règlement complété avec les informations connues (Paramètres du fonds) ;
     * les trésoriers listés sont toutes les personnes qui ont le rôle Trésorier.
     */
    public static function filled(string $locale = 'fr'): array
    {
        $values = [
            'association_name' => FundRules::get('association_name'),
            'governing_state' => FundRules::get('governing_state'),
            'bank_name' => FundRules::get('bank_name'),
            'bank_account_numbers' => FundRules::get('bank_account_numbers'),
            'bank_location' => FundRules::get('bank_location'),
            'first_payment_date' => FundRules::get('first_payment_date') ? \Carbon\Carbon::parse(FundRules::get('first_payment_date'))->format($locale === 'en' ? 'm/d/Y' : 'd/m/Y') : '',
        ];
        $map = [
            '{État}' => 'governing_state', '{State}' => 'governing_state',
            '{Nom de l’association à but non lucratif}' => 'association_name', '{Name of the non-profit association}' => 'association_name',
            '{Banque}' => 'bank_name', '{Bank}' => 'bank_name',
            '{Numéros}' => 'bank_account_numbers', '{Numbers}' => 'bank_account_numbers',
            '{Ville, État}' => 'bank_location', '{City, State}' => 'bank_location',
            '{Date}' => 'first_payment_date',
        ];
        // Montants du règlement : valeurs paramétrées, dans la devise du fonds.
        $quarterly = (float) FundRules::get('contribution_quarterly_amount');
        $values['quarterly'] = FundCurrency::short($quarterly);
        $values['monthly'] = FundCurrency::short($quarterly / 3);
        $values['penalty'] = FundCurrency::short(FundRules::get('contribution_penalty_amount'));
        $map += ['{quarterly}' => 'quarterly', '{monthly}' => 'monthly', '{penalty}' => 'penalty'];
        $fill = fn (string $text) => strtr($text, array_filter(array_map(fn ($key) => $values[$key] ?: null, $map)));

        $treasurers = User::role('treasurer')->with('treasuredZones')->orderBy('name')->get()
            ->map(fn (User $u) => [$locale === 'en' ? 'Treasurer' : 'Trésorier', $u->name, $u->treasuredZones->pluck('name')->map(fn ($z) => $locale === 'en' && $z === 'Amérique' ? 'America' : $z)->implode(', ') ?: '—'])
            ->all();

        return array_map(function (array $section) use ($fill, $treasurers) {
            $section['blocks'] = array_map(function (array $block) use ($fill, $treasurers, $section) {
                if ($block[0] === 'table' && $section['id'] === 'membres' && $treasurers) {
                    return ['table', $block[1], $treasurers];
                }

                return match ($block[0]) {
                    'ul' => ['ul', array_map($fill, $block[1])],
                    'table' => ['table', $block[1], array_map(fn ($row) => array_map($fill, $row), $block[2])],
                    default => [$block[0], $fill($block[1])],
                };
            }, $section['blocks']);

            return $section;
        }, self::sections($locale));
    }

    private static function french(): array
    {
        return [
            ['id' => 'objet', 'title' => '1. Objet du fonds', 'blocks' => [
                ['p', 'Le fonds familial est un outil créé par notre famille, pour notre famille. Son objectif est simple : mettre de l’argent en commun chaque trimestre pour constituer un fonds qui peut aider tout membre dans le besoin. C’est un projet fondé sur la confiance, la discipline et la solidarité.'],
                ['p', 'Chaque personne qui participe devient Membre du fonds. Cela signifie qu’elle a le droit de voter, de faire des propositions et de demander un prêt selon les règles fixées dans ce document.'],
                ['note', 'Tous les versements faits par le Fonds Familial JOKUNG à un membre selon la Section 5 sont des prêts : ce sont des obligations de remboursement qui engagent légalement le membre emprunteur. Aucun versement prévu par ce règlement n’est un cadeau, un don ou un transfert gratuit. Le lien familial entre les parties ne change rien à l’obligation de rembourser et ne la réduit pas. Aucun prêt ne peut être annulé, effacé, réduit ou pardonné oralement par un membre, un trésorier ou toute autre personne, sans une modification écrite approuvée par 80 % des membres actifs, conformément à la Section 9.'],
                ['p', 'Ce fonds et toutes les opérations qui y sont liées sont régis par les lois de l’État de {État}, USA, et doivent être interprétés selon ces lois.'],
            ]],
            ['id' => 'membres', 'title' => '2. Membres et trésoriers', 'blocks' => [
                ['h', '2.1 Membres'],
                ['p', 'Tout membre de la famille qui contribue régulièrement est considéré comme Membre. Il a accès à toutes les informations sur le fonctionnement du fonds et participe aux décisions collectives.'],
                ['h', '2.2 Trésoriers'],
                ['p', 'Deux trésoriers sont nommés pour gérer le fonds : un en Europe et un en Amérique. Ils sont responsables de :'],
                ['ul', [
                    'Recevoir les contributions des membres de leur zone',
                    'Transférer l’argent vers le compte commun détenu par l’Association Fonds Familial JOKUNG',
                    'Tenir à jour le registre des paiements',
                    'Informer le groupe en cas de retard ou de problème',
                    'Tenir à jour, en temps réel, un registre des membres (sous Excel ou un autre outil similaire) indiquant, pour chaque membre : (i) toutes les contributions reçues, avec la date et le montant ; (ii) le total cumulé des contributions ; (iii) la part de chaque membre dans le fonds, en pourcentage du total des contributions ; (iv) tous les prêts versés et remboursés, avec les dates ; et (v) les pénalités appliquées. Ce registre doit toujours correspondre exactement au solde du compte bancaire.',
                ]],
                ['p', 'Un trésorier ne peut pas participer, traiter ou voter sur une demande de prêt dont il est lui-même le demandeur. Dans ce cas, le groupe nommera un co-trésorier temporaire parmi les membres, uniquement pour cette opération.'],
                ['p', 'Si un trésorier démissionne ou est démis de ses fonctions par un vote du groupe (80 % requis), un successeur doit être proposé et confirmé par le groupe dans un délai de 30 jours. Le trésorier sortant doit transmettre tous les documents, registres et accès au compte à son successeur dans les 7 jours suivant la confirmation.'],
                ['table', ['Rôle', 'Nom', 'Zone'], [['Trésorier', '{Nom}', 'Europe'], ['Trésorier', '{Nom}', 'Amérique']]],
            ]],
            ['id' => 'compte', 'title' => '3. Compte bancaire', 'blocks' => [
                ['p', 'Le fonds commun sera hébergé par une association à but non lucratif, {Nom de l’association à but non lucratif}, à enregistrer dans l’État de {État}, USA. Le compte commun du fonds sera ouvert au nom de l’Association, plutôt qu’au nom personnel de deux membres. Les dirigeants/le conseil de l’Association (plutôt que deux personnes agissant à titre personnel) seront responsables de l’ouverture et de la gestion de ce compte au nom du fonds familial.'],
                ['p', 'Tout l’argent reçu par les trésoriers (Europe et Amérique) sera transféré vers ce compte commun chaque trimestre, après la date limite de contribution.'],
                ['p', 'Les informations pratiques du compte (banque, numéro de routage et de compte, instructions de virement) seront communiquées à tous les membres avant le début des contributions.'],
                ['table', ['Information', 'Valeur'], [['Titulaires du compte', '{Nom de l’association à but non lucratif}'], ['Banque', '{Banque}'], ['Numéro de routage et de compte', '{Numéros}'], ['Zone', '{Ville, État}, USA']]],
                ['note', 'Tout versement depuis le compte commun du fonds — que ce soit un prêt, un remboursement ou un virement — doit être approuvé par écrit par les deux trésoriers (ou par une personne désignée pour les remplacer). Personne ne peut autoriser ou effectuer un retrait seul.'],
                ['p', 'Tout intérêt, revenu de placement ou rendement bancaire généré par le solde du compte commun reste dans le fonds et est inscrit comme un revenu dans le registre. Il n’est pas distribué aux Membres, sauf en cas de dissolution du fonds ou de retrait selon la Section 8.'],
            ]],
            ['id' => 'contributions', 'title' => '4. Contributions trimestrielles', 'blocks' => [
                ['h', '4.1 Montant'],
                ['p', 'Chaque Membre doit payer une contribution trimestrielle fixe (tous les 3 mois), selon sa zone :'],
                ['ul', ['{quarterly} ({monthly} × 3 mois)']],
                ['p', 'Les membres qui souhaitent contribuer davantage peuvent le faire volontairement, en informant le trésorier par écrit. Aucun membre ne doit être poussé, sollicité ou encouragé à dépasser la contribution minimale. Le montant minimum ne peut être changé que par un vote du groupe, selon la Section 9.'],
                ['h', '4.2 Date limite'],
                ['p', 'Les contributions doivent être reçues par le trésorier de la zone de chaque Membre au plus tard le 15 du premier mois de chaque trimestre (soit tous les 3 mois). Le premier paiement est attendu avant le {Date}. Le trésorier confirmera la réception de chaque paiement au membre dans un délai de 2 jours ouvrés.'],
                ['h', '4.3 Pénalité de retard'],
                ['p', 'Toute contribution reçue après le 15 du mois recevra une pénalité de retard de {penalty} par semaine, à partir du 16. Si le retard est dû à une erreur bancaire ou à un échec de virement documenté (avec un justificatif de transaction remis au trésorier), la pénalité est annulée pour ce trimestre-là. La pénalité sera ajoutée à la contribution du trimestre suivant et inscrite dans le registre.'],
            ]],
            ['id' => 'prets', 'title' => '5. Demandes de prêt', 'blocks' => [
                ['p', 'Le fonds pourra accorder des prêts aux Membres à partir de janvier 2027. Un prêt est de l’argent prêté par le fonds à un membre pour l’aider à réaliser un projet. Le membre s’engage à le rembourser selon les conditions convenues.'],
                ['h', '5.1 Qui peut demander un prêt ?'],
                ['note', 'Seuls les membres qui contribuent au fonds peuvent demander ou recevoir un prêt. Les membres de la famille qui ne contribuent pas ne peuvent pas emprunter au fonds.'],
                ['p', 'Un Membre ne peut pas demander un nouveau prêt tant qu’un prêt précédent n’est pas entièrement remboursé. Il ne redevient éligible à un nouveau prêt qu’une fois l’ancien prêt totalement remboursé, intérêts et pénalités compris.'],
                ['h', '5.2 Prévenir le groupe à l’avance'],
                ['p', 'Avant de présenter son projet, le membre doit prévenir le groupe entre 15 et 30 jours à l’avance. Cela permet à tous les membres de se rendre disponibles pour la réunion de présentation.'],
                ['h', '5.3 Présentation du projet'],
                ['p', 'Le membre présente son projet lors d’une réunion (appel vidéo). La présentation doit inclure :'],
                ['ul', ['La nature du projet (à quoi servira l’argent ?)', 'Le montant demandé', 'Le plan de remboursement proposé', 'Toute information utile pour aider les membres à décider']],
                ['h', '5.4 Vote'],
                ['p', 'Après la présentation, les membres ont 7 jours pour voter. Le vote est anonyme (par sondage ou message privé au trésorier).'],
                ['ul', [
                    'Pour que le prêt soit approuvé : 80 % des votes doivent être favorables',
                    'Si le prêt est refusé : (a) le membre ne peut pas soumettre à nouveau le même projet avant 3 mois ; (b) le membre peut demander un prêt pour un projet différent, sans lien avec le premier, à tout moment après 30 jours.',
                ]],
                ['h', '5.5 Conditions du prêt'],
                ['p', 'Le fonds ne peut jamais prêter plus de 50 % de son solde total au moment de la demande. Cette règle protège le fonds commun et garantit que de l’argent reste disponible pour les autres membres. Si le montant demandé dépasse cette limite, la demande est automatiquement réduite ou refusée.'],
                ['p', 'Si le prêt est approuvé, les conditions suivantes s’appliquent :'],
                ['table', ['Condition', 'Règle'], [
                    ['Montant maximum', '50 % du solde total disponible au moment de la demande'],
                    ['Durée maximale de remboursement', '12 mois à partir de la date de versement. Aucun calendrier de remboursement ne peut dépasser cette limite, même en cas de modification ou de restructuration d’un prêt existant.'],
                    ['Taux d’intérêt', '4 % (taux annuel)'],
                    ['Pénalité de retard de remboursement', '1 % du montant de l’échéance impayée, par mois, à partir du premier jour suivant la date d’échéance manquée, sans aucun délai de grâce supplémentaire.'],
                ]],
                ['p', 'Un accord écrit, signé par le membre emprunteur et les deux trésoriers, sera établi avant tout versement de fonds.'],
                ['h', '5.6 Remboursement'],
                ['p', 'Les remboursements sont faits directement au trésorier de la zone du membre, selon le calendrier prévu dans l’accord. Tout retard de remboursement sera signalé au groupe après un délai de grâce de 10 jours.'],
                ['ul', ['1er rappel : message privé du trésorier', '2e rappel (après 15 jours) : le groupe est informé', '3e rappel (après 30 jours) : décision collective sur la suite à donner']],
            ]],
            ['id' => 'transparence', 'title' => '6. Transparence et rapports', 'blocks' => [
                ['p', 'Les trésoriers partageront un rapport lors de la réunion trimestrielle avec tous les membres. Ce rapport inclura :'],
                ['ul', ['Le total des contributions reçues', 'Les retards de paiement ou pénalités appliquées', 'Le solde total du compte', 'L’état des remboursements en cours']],
                ['p', 'Ce rapport sera aussi partagé dans le groupe.'],
                ['p', 'Une fois par an, avant fin février, un ou deux membres (autres que les trésoriers), choisis par vote du groupe, examineront les registres et relevés bancaires de l’année précédente et confirmeront par écrit au groupe que les comptes sont exacts et complets. Cet examen ne demande pas de compétences professionnelles en comptabilité, mais doit être documenté par écrit.'],
            ]],
            ['id' => 'integration', 'title' => '7. Intégration de nouveaux membres', 'blocks' => [
                ['p', 'Toute personne qui souhaite rejoindre le fonds familial doit prévenir le groupe au moins un mois à l’avance. Les nouveaux membres sont admis une fois par an, à la fin du cycle annuel du fonds. Un nouveau membre ne peut demander un prêt qu’après avoir contribué pendant au moins 6 mois consécutifs (2 contributions trimestrielles consécutives).'],
            ]],
            ['id' => 'retrait', 'title' => '8. Retrait d’un membre', 'blocks' => [
                ['p', 'Toute personne qui souhaite quitter le fonds familial doit prévenir le groupe au moins deux mois à l’avance, en expliquant la raison de son départ. Le remboursement des contributions se fait comme suit :'],
                ['ul', [
                    'Le membre reçoit au maximum 95 % du total de ses contributions. Les 5 % restants sont conservés par le fonds et ne sont pas remboursables.',
                    'Si l’argent n’est pas engagé dans un prêt en cours, le remboursement a lieu entre 1 et 6 mois.',
                    'Si l’argent est engagé dans un prêt en cours, le remboursement peut prendre plus de temps, mais ne dépassera pas 12 mois à partir de la date officielle du départ.',
                    'Le calendrier de remboursement sera communiqué au membre dans les deux semaines suivant sa demande.',
                ]],
            ]],
            ['id' => 'modification', 'title' => '9. Modification des règles', 'blocks' => [
                ['p', 'Toute modification de ces règles doit être approuvée par un vote du groupe (80 % requis). Aucune règle ne peut être changée par une seule personne, y compris les trésoriers.'],
            ]],
            ['id' => 'acceptation', 'title' => 'Acceptation des membres', 'blocks' => [
                ['p', 'En participant au fonds, chaque membre confirme avoir lu et accepté les règles de ce document.'],
            ]],
        ];
    }

    private static function english(): array
    {
        return [
            ['id' => 'objet', 'title' => '1. Purpose of the fund', 'blocks' => [
                ['p', 'The family fund is a tool created by our family, for our family. Its goal is simple: pool money every quarter to build a fund that can help any member in need. It is a project built on trust, discipline and solidarity.'],
                ['p', 'Everyone who takes part becomes a Member of the fund. This means they have the right to vote, make proposals and request a loan under the rules set out in this document.'],
                ['note', 'All payments made by the JOKUNG Family Fund to a member under Section 5 are loans: they are repayment obligations that legally bind the borrowing member. No payment under these rules is a gift, a donation or a free transfer. The family relationship between the parties does not change or reduce the obligation to repay. No loan may be cancelled, written off, reduced or forgiven orally by a member, a treasurer or anyone else, without a written amendment approved by 80% of active members, in accordance with Section 9.'],
                ['p', 'This fund and all related operations are governed by the laws of the State of {State}, USA, and must be interpreted under those laws.'],
            ]],
            ['id' => 'membres', 'title' => '2. Members and treasurers', 'blocks' => [
                ['h', '2.1 Members'],
                ['p', 'Any family member who contributes regularly is considered a Member. They have access to all information about how the fund works and take part in collective decisions.'],
                ['h', '2.2 Treasurers'],
                ['p', 'Two treasurers are appointed to manage the fund: one in Europe and one in America. They are responsible for:'],
                ['ul', [
                    'Receiving contributions from the members of their zone',
                    'Transferring the money to the joint account held by the JOKUNG Family Fund Association',
                    'Keeping the payment register up to date',
                    'Informing the group of any delay or problem',
                    'Keeping a real-time member register (in Excel or a similar tool) showing, for each member: (i) every contribution received, with its date and amount; (ii) the cumulative total of contributions; (iii) each member’s share of the fund, as a percentage of total contributions; (iv) all loans paid out and repaid, with dates; and (v) penalties applied. This register must always match the bank account balance exactly.',
                ]],
                ['p', 'A treasurer may not take part in, process or vote on a loan request they have made themselves. In that case, the group will appoint a temporary co-treasurer from among the members, for that operation only.'],
                ['p', 'If a treasurer resigns or is removed by a group vote (80% required), a successor must be proposed and confirmed by the group within 30 days. The outgoing treasurer must hand over all documents, registers and account access to their successor within 7 days of confirmation.'],
                ['table', ['Role', 'Name', 'Zone'], [['Treasurer', '{Name}', 'Europe'], ['Treasurer', '{Name}', 'America']]],
            ]],
            ['id' => 'compte', 'title' => '3. Bank account', 'blocks' => [
                ['p', 'The common fund will be held by a non-profit association, {Name of the non-profit association}, to be registered in the State of {State}, USA. The fund’s joint account will be opened in the name of the Association, rather than in the personal names of two members. The officers/board of the Association (rather than two people acting personally) will be responsible for opening and managing this account on behalf of the family fund.'],
                ['p', 'All money received by the treasurers (Europe and America) will be transferred to this joint account every quarter, after the contribution deadline.'],
                ['p', 'The practical account details (bank, routing and account numbers, wire instructions) will be shared with all members before contributions begin.'],
                ['table', ['Information', 'Value'], [['Account holders', '{Name of the non-profit association}'], ['Bank', '{Bank}'], ['Routing and account number', '{Numbers}'], ['Location', '{City, State}, USA']]],
                ['note', 'Any payment from the fund’s joint account — whether a loan, a refund or a transfer — must be approved in writing by both treasurers (or by a person designated to replace them). No one may authorise or make a withdrawal alone.'],
                ['p', 'Any interest, investment income or bank return generated by the joint account balance stays in the fund and is recorded as income in the register. It is not distributed to Members, except if the fund is dissolved or a member withdraws under Section 8.'],
            ]],
            ['id' => 'contributions', 'title' => '4. Quarterly contributions', 'blocks' => [
                ['h', '4.1 Amount'],
                ['p', 'Each Member must pay a fixed quarterly contribution (every 3 months), according to their zone:'],
                ['ul', ['{quarterly} ({monthly} × 3 months)']],
                ['p', 'Members who wish to contribute more may do so voluntarily, by informing the treasurer in writing. No member should be pushed, solicited or encouraged to exceed the minimum contribution. The minimum amount can only be changed by a group vote, under Section 9.'],
                ['h', '4.2 Deadline'],
                ['p', 'Contributions must be received by the treasurer of each Member’s zone no later than the 15th of the first month of each quarter (every 3 months). The first payment is due before {Date}. The treasurer will confirm receipt of each payment to the member within 2 business days.'],
                ['h', '4.3 Late penalty'],
                ['p', 'Any contribution received after the 15th of the month incurs a late penalty of {penalty} per week, starting on the 16th. If the delay is due to a bank error or a documented transfer failure (with transaction proof given to the treasurer), the penalty is waived for that quarter. The penalty is added to the next quarter’s contribution and recorded in the register.'],
            ]],
            ['id' => 'prets', 'title' => '5. Loan requests', 'blocks' => [
                ['p', 'The fund may grant loans to Members from January 2027. A loan is money lent by the fund to a member to help them carry out a project. The member commits to repaying it under the agreed terms.'],
                ['h', '5.1 Who can request a loan?'],
                ['note', 'Only members who contribute to the fund can request or receive a loan. Family members who do not contribute cannot borrow from the fund.'],
                ['p', 'A Member cannot request a new loan while a previous loan has not been fully repaid. They become eligible again only once the previous loan has been repaid in full, including interest and penalties.'],
                ['h', '5.2 Notify the group in advance'],
                ['p', 'Before presenting their project, the member must notify the group 15 to 30 days in advance, so that all members can make themselves available for the presentation meeting.'],
                ['h', '5.3 Project presentation'],
                ['p', 'The member presents the project during a meeting (video call). The presentation must include:'],
                ['ul', ['The nature of the project (what will the money be used for?)', 'The amount requested', 'The proposed repayment plan', 'Any information that helps members decide']],
                ['h', '5.4 Vote'],
                ['p', 'After the presentation, members have 7 days to vote. The vote is anonymous (by poll or private message to the treasurer).'],
                ['ul', [
                    'For the loan to be approved: 80% of the votes must be in favour',
                    'If the loan is refused: (a) the member may not resubmit the same project for 3 months; (b) the member may request a loan for a different, unrelated project at any time after 30 days.',
                ]],
                ['h', '5.5 Loan conditions'],
                ['p', 'The fund may never lend more than 50% of its total balance at the time of the request. This rule protects the common fund and ensures money remains available for other members. If the amount requested exceeds this limit, the request is automatically reduced or refused.'],
                ['p', 'If the loan is approved, the following conditions apply:'],
                ['table', ['Condition', 'Rule'], [
                    ['Maximum amount', '50% of the total available balance at the time of the request'],
                    ['Maximum repayment period', '12 months from the payment date. No repayment schedule may exceed this limit, even if an existing loan is modified or restructured.'],
                    ['Interest rate', '4% (annual rate)'],
                    ['Late repayment penalty', '1% of the unpaid instalment, per month, from the first day after the missed due date, with no additional grace period.'],
                ]],
                ['p', 'A written agreement, signed by the borrowing member and both treasurers, will be drawn up before any funds are paid out.'],
                ['h', '5.6 Repayment'],
                ['p', 'Repayments are made directly to the treasurer of the member’s zone, according to the schedule set out in the agreement. Any late repayment will be reported to the group after a 10-day grace period.'],
                ['ul', ['1st reminder: private message from the treasurer', '2nd reminder (after 15 days): the group is informed', '3rd reminder (after 30 days): collective decision on next steps']],
            ]],
            ['id' => 'transparence', 'title' => '6. Transparency and reports', 'blocks' => [
                ['p', 'The treasurers will share a report at the quarterly meeting with all members. This report will include:'],
                ['ul', ['Total contributions received', 'Late payments or penalties applied', 'Total account balance', 'Status of ongoing repayments']],
                ['p', 'This report will also be shared in the group.'],
                ['p', 'Once a year, before the end of February, one or two members (other than the treasurers), chosen by group vote, will review the previous year’s registers and bank statements and confirm in writing to the group that the accounts are accurate and complete. This review does not require professional accounting skills, but must be documented in writing.'],
            ]],
            ['id' => 'integration', 'title' => '7. Welcoming new members', 'blocks' => [
                ['p', 'Anyone who wishes to join the family fund must notify the group at least one month in advance. New members are admitted once a year, at the end of the fund’s annual cycle. A new member may only request a loan after contributing for at least 6 consecutive months (2 consecutive quarterly contributions).'],
            ]],
            ['id' => 'retrait', 'title' => '8. Leaving the fund', 'blocks' => [
                ['p', 'Anyone who wishes to leave the family fund must notify the group at least two months in advance, explaining the reason for leaving. Contributions are refunded as follows:'],
                ['ul', [
                    'The member receives at most 95% of their total contributions. The remaining 5% is kept by the fund and is non-refundable.',
                    'If the money is not tied up in an ongoing loan, the refund takes place within 1 to 6 months.',
                    'If the money is tied up in an ongoing loan, the refund may take longer, but will not exceed 12 months from the official departure date.',
                    'The refund schedule will be shared with the member within two weeks of their request.',
                ]],
            ]],
            ['id' => 'modification', 'title' => '9. Changing the rules', 'blocks' => [
                ['p', 'Any change to these rules must be approved by a group vote (80% required). No rule can be changed by a single person, including the treasurers.'],
            ]],
            ['id' => 'acceptation', 'title' => 'Members’ acceptance', 'blocks' => [
                ['p', 'By taking part in the fund, each member confirms they have read and accepted the rules in this document.'],
            ]],
        ];
    }
}
