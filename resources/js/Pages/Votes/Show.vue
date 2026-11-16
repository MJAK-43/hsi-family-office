<script setup>
import { tr } from '@/i18n';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Ban, XCircle, Vote as VoteIcon } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';

const props = defineProps({
    vote: Object,
    userBallot: Object,
    ballots: Array,
    activities: Array,
    can: Object,
});

const ballotForm = useForm({
    option: '',
    comment: '',
});
const conflictForm = useForm({ reason: '' });

const castBallot = () => {
    ballotForm.post(route('votes.cast', props.vote.id));
};

const closeVote = () => {
    if (confirm(tr('Clôturer ce vote et calculer le résultat ?'))) {
        router.patch(route('votes.close', props.vote.id));
    }
};

const cancelVote = () => {
    if (confirm(tr('Annuler définitivement ce vote ?'))) {
        router.patch(route('votes.cancel', props.vote.id));
    }
};

const optionColor = (value) => {
    if (value === 'for') return 'text-hsi-success';
    if (value === 'against') return 'text-hsi-danger';
    return 'text-hsi-slate';
};

const declareConflict = () => conflictForm.post(route('votes.conflicts.store', props.vote.id), { onSuccess: () => conflictForm.reset() });
const resolveConflict = (conflict, confirmed) => router.patch(route('votes.conflicts.resolve', conflict.id), { confirmed, resolution_note: confirmed ? 'Conflit confirmé' : 'Déclaration rejetée' });
</script>

<template>
    <Head :title="vote.title" />

    <AuthenticatedLayout>
        <HsiPageHeader
            :title="vote.title"
            :subtitle="vote.type_label"
        >
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <HsiButton
                        v-if="can.close"
                        @click="closeVote"
                    >
                        <Check class="h-4 w-4" />
                        Clôturer
                    </HsiButton>
                    <HsiButton
                        v-if="can.cancel"
                        variant="danger"
                        @click="cancelVote"
                    >
                        <Ban class="h-4 w-4" />
                        Annuler
                    </HsiButton>
                </div>
            </template>
        </HsiPageHeader>

        <div class="mb-6 flex flex-wrap items-center gap-3">
            <HsiBadge
                :variant="vote.status_variant"
                size="lg"
            >
                {{ vote.status_label }}
            </HsiBadge>
            <HsiBadge
                v-if="vote.result_label"
                :variant="vote.result_variant"
            >
                {{ vote.result_label }}
            </HsiBadge>
            <span class="text-sm text-hsi-slate">
                Échéance : <strong>{{ vote.closes_at || '—' }}</strong>
            </span>
            <span class="text-sm text-hsi-slate">Bulletin : <strong>{{ vote.ballot_visibility_label }}</strong></span>
            <span v-if="vote.winning_option" class="text-sm text-hsi-slate">Option retenue : <strong>{{ vote.winning_option }}</strong></span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <HsiCard padding="lg">
                    <HsiSectionTitle title="Participation" />
                    <div class="mb-4">
                        <div class="flex justify-between text-sm mb-2">
                            <span>{{ vote.ballots_count }} / {{ vote.eligible_count }} électeurs</span>
                            <span class="font-semibold">{{ vote.participation_label }}</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-hsi-muted overflow-hidden">
                            <div
                                class="h-full rounded-full bg-hsi-gold transition-all"
                                :style="{ width: `${Math.min(vote.participation, 100)}%` }"
                            />
                        </div>
                        <p class="mt-2 text-xs text-hsi-slate">
                            Quorum {{ vote.quorum_percent }} % —
                            <span :class="vote.quorum_reached ? 'text-hsi-success' : 'text-hsi-warning'">
                                {{ vote.quorum_reached ? 'atteint' : 'non atteint' }}
                            </span>
                        </p>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-center">
                        <div v-for="option in vote.options" :key="option.id" class="rounded-xl border p-3" :class="option.is_winning ? 'border-hsi-gold bg-hsi-gold/5' : 'border-hsi-border'">
                            <p class="text-2xl font-bold" :class="optionColor(option.value)">{{ option.count }}</p>
                            <p class="text-xs text-hsi-slate">{{ option.label }}</p>
                        </div>
                    </div>
                </HsiCard>

                <HsiCard
                    v-if="vote.description"
                    padding="lg"
                >
                    <HsiSectionTitle title="Description" />
                    <p class="text-sm text-hsi-slate whitespace-pre-wrap">{{ vote.description }}</p>
                    <p
                        v-if="vote.project"
                        class="mt-3 text-sm"
                    >
                        Projet lié :
                        <Link
                            v-if="vote.project_id"
                            :href="route('projects.show', vote.project_id)"
                            class="text-hsi-gold hover:underline font-medium"
                        >
                            {{ vote.project }}
                        </Link>
                    </p>
                </HsiCard>

                <HsiCard v-if="['draft', 'scheduled'].includes(vote.status)" padding="lg">
                    <HsiSectionTitle title="Conflits d’intérêts" />
                    <form class="flex gap-2" @submit.prevent="declareConflict">
                        <input v-model="conflictForm.reason" required minlength="5" class="flex-1 rounded-xl border-hsi-border text-sm" placeholder="Déclarer mon conflit d’intérêts" />
                        <HsiButton type="submit" variant="outline">Déclarer</HsiButton>
                    </form>
                    <ul v-if="vote.conflicts?.length" class="mt-4 space-y-2">
                        <li v-for="conflict in vote.conflicts" :key="conflict.id" class="rounded-xl border border-hsi-border p-3 text-sm flex justify-between gap-3">
                            <span><strong>{{ conflict.user }}</strong> — {{ conflict.reason }} ({{ conflict.status }})</span>
                            <span v-if="can.manageConflicts && conflict.status === 'pending'" class="flex gap-2">
                                <button class="text-hsi-success" @click="resolveConflict(conflict, true)">Confirmer</button>
                                <button class="text-hsi-danger" @click="resolveConflict(conflict, false)">Rejeter</button>
                            </span>
                        </li>
                    </ul>
                </HsiCard>

                <HsiCard
                    v-if="can.cast"
                    padding="lg"
                >
                    <HsiSectionTitle title="Voter" />
                    <form
                        class="space-y-4"
                        @submit.prevent="castBallot"
                    >
                        <div class="space-y-2">
                            <label
                                v-for="opt in vote.options"
                                :key="opt.value"
                                class="flex items-center gap-3 rounded-xl border border-hsi-border p-3 cursor-pointer hover:border-hsi-gold/50"
                                :class="ballotForm.option === opt.value ? 'border-hsi-gold bg-hsi-gold/5' : ''"
                            >
                                <input
                                    v-model="ballotForm.option"
                                    type="radio"
                                    :value="opt.value"
                                    required
                                />
                                <span
                                    class="font-medium"
                                    :class="optionColor(opt.value)"
                                >{{ opt.label }}</span>
                            </label>
                        </div>
                        <p
                            v-if="ballotForm.errors.option"
                            class="text-xs text-hsi-danger"
                        >
                            {{ ballotForm.errors.option }}
                        </p>
                        <div>
                            <label class="block text-sm font-medium text-hsi-slate mb-1">Commentaire (optionnel)</label>
                            <textarea
                                v-model="ballotForm.comment"
                                rows="2"
                                class="w-full rounded-xl border border-hsi-border px-3 py-2 text-sm"
                                maxlength="500"
                            />
                        </div>
                        <HsiButton
                            type="submit"
                            :disabled="ballotForm.processing || !ballotForm.option"
                        >
                            <VoteIcon class="h-4 w-4" />
                            Enregistrer mon vote
                        </HsiButton>
                    </form>
                </HsiCard>

                <HsiCard
                    v-else-if="userBallot"
                    padding="lg"
                >
                    <HsiSectionTitle title="Votre vote" />
                    <p class="text-sm">
                        Vous avez voté
                        <strong :class="optionColor(userBallot.option_value)">{{ userBallot.option }}</strong>
                        le {{ userBallot.cast_at }}.
                    </p>
                    <p
                        v-if="userBallot.comment"
                        class="mt-2 text-sm italic text-hsi-slate"
                    >
                        « {{ userBallot.comment }} »
                    </p>
                </HsiCard>

                <HsiCard
                    v-if="can.viewBallots && ballots?.length"
                    padding="lg"
                >
                    <HsiSectionTitle title="Bulletins détaillés" />
                    <ul class="space-y-2">
                        <li
                            v-for="b in ballots"
                            :key="b.id"
                            class="flex justify-between text-sm border-b border-hsi-border/50 pb-2"
                        >
                            <span>
                                <strong>{{ b.user }}</strong>
                                —
                                <span :class="optionColor(b.option_value)">{{ b.option }}</span>
                            </span>
                            <span class="text-hsi-slate-light">{{ b.cast_at }}</span>
                        </li>
                    </ul>
                </HsiCard>
            </div>

            <div class="space-y-6">
                <HsiCard padding="lg">
                    <HsiSectionTitle title="Règles" />
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="text-hsi-slate-light">Majorité</dt>
                            <dd>{{ vote.majority_rule }}</dd>
                        </div>
                        <div>
                            <dt class="text-hsi-slate-light">Quorum</dt>
                            <dd>{{ vote.quorum_percent }} %</dd>
                        </div>
                        <div>
                            <dt class="text-hsi-slate-light">Ouverture</dt>
                            <dd>{{ vote.opens_at || '—' }}</dd>
                        </div>
                        <div v-if="vote.closed_at">
                            <dt class="text-hsi-slate-light">Clôture</dt>
                            <dd>{{ vote.closed_at }} ({{ vote.closed_by }})</dd>
                        </div>
                    </dl>
                </HsiCard>

                <HsiCard padding="lg">
                    <HsiSectionTitle title="Activité" />
                    <ul class="space-y-3">
                        <li
                            v-for="activity in activities"
                            :key="activity.id"
                            class="text-sm border-b border-hsi-border/50 pb-2 last:border-0"
                        >
                            <p class="text-hsi-navy">{{ activity.description }}</p>
                            <p class="text-xs text-hsi-slate-light">
                                {{ activity.causer ?? 'Système' }} — {{ activity.created_at }}
                            </p>
                        </li>
                        <li
                            v-if="!activities?.length"
                            class="text-sm text-hsi-slate-light"
                        >
                            Aucune activité.
                        </li>
                    </ul>
                </HsiCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
