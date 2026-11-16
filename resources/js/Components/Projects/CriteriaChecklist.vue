<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Bot, CheckCircle2, CircleDashed, MinusCircle, XCircle } from 'lucide-vue-next';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';

const props = defineProps({
    project: { type: Object, required: true },
    criteria: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    decisions: { type: Array, default: () => [] },
    canReview: { type: Boolean, default: false },
});

const editing = ref(null);
const comment = ref('');
const startEdit = (criterion) => { editing.value = criterion.id; comment.value = criterion.comment ?? ''; };
const setStatus = (criterion, status) => router.patch(route('projects.criteria.check', criterion.id), { status, comment: comment.value || criterion.comment || null }, {
    preserveScroll: true,
    onSuccess: () => { editing.value = null; },
});

const blockingOpen = computed(() => props.criteria.filter((c) => c.is_blocking && !['validated', 'not_applicable'].includes(c.status)));
const counts = computed(() => ({
    validated: props.criteria.filter((c) => c.status === 'validated').length,
    total: props.criteria.length,
}));

const decisionForm = useForm({ decision: 'accept', reason: '' });
const decide = () => decisionForm.patch(route('projects.decide', props.project.id), { preserveScroll: true });

const icon = (status) => ({ validated: CheckCircle2, not_validated: XCircle, not_applicable: MinusCircle }[status] ?? CircleDashed);
const tone = (status) => ({ validated: 'text-hsi-success', not_validated: 'text-hsi-danger', not_applicable: 'text-hsi-slate-light' }[status] ?? 'text-amber-500');
</script>

<template>
    <HsiCard padding="lg">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <HsiSectionTitle title="Critères du dossier" />
            <span class="text-sm text-hsi-slate">{{ counts.validated }} / {{ counts.total }} validés</span>
        </div>

        <ul class="divide-y divide-hsi-border/60">
            <li v-for="criterion in criteria" :key="criterion.id" class="py-3">
                <div class="flex items-start gap-3">
                    <component :is="icon(criterion.status)" class="mt-0.5 h-5 w-5 shrink-0" :class="tone(criterion.status)" aria-hidden="true" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-hsi-navy">{{ criterion.label }}</p>
                            <span v-if="criterion.is_blocking" class="rounded-full bg-hsi-navy/5 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-hsi-navy">Bloquant</span>
                            <span class="text-xs font-semibold" :class="tone(criterion.status)">{{ criterion.status_label }}</span>
                        </div>
                        <p v-if="criterion.description" class="text-xs text-hsi-slate-light">{{ criterion.description }}</p>
                        <p v-if="criterion.auto_detail" class="mt-1 flex items-center gap-1 text-xs text-hsi-slate"><Bot class="h-3.5 w-3.5" aria-hidden="true" /> Vérification automatique : {{ criterion.auto_detail }}</p>
                        <p v-if="criterion.comment" class="mt-1 rounded-lg bg-hsi-muted/60 px-2 py-1 text-sm text-hsi-slate">« {{ criterion.comment }} »</p>
                        <p v-if="criterion.checked_at" class="mt-0.5 text-[11px] text-hsi-slate-light">Vérifié le {{ criterion.checked_at }}<template v-if="criterion.checked_by"> par {{ criterion.checked_by }}</template></p>

                        <div v-if="canReview" class="mt-2">
                            <div v-if="editing === criterion.id" class="space-y-2">
                                <textarea v-model="comment" rows="2" class="w-full rounded-xl border border-hsi-border px-3 py-2 text-sm" placeholder="Commentaire (visible par le demandeur)" />
                                <div class="flex flex-wrap gap-2">
                                    <HsiButton v-for="status in statuses.filter((s) => s.value !== 'pending')" :key="status.value" size="sm" :variant="status.value === 'not_validated' ? 'danger' : (status.value === 'validated' ? 'primary' : 'outline')" @click="setStatus(criterion, status.value)">{{ status.label }}</HsiButton>
                                    <HsiButton size="sm" variant="ghost" @click="editing = null">Annuler</HsiButton>
                                </div>
                            </div>
                            <button v-else type="button" class="text-xs font-semibold text-[#1D4ED8] hover:underline" @click="startEdit(criterion)">Vérifier ce critère</button>
                        </div>
                    </div>
                </div>
            </li>
            <li v-if="!criteria.length" class="py-3 text-sm text-hsi-slate-light">La liste de critères sera copiée au dépôt de la demande.</li>
        </ul>

        <form v-if="canReview" class="mt-5 space-y-3 rounded-2xl border border-hsi-border bg-hsi-muted/30 p-4" @submit.prevent="decide">
            <h3 class="font-semibold text-hsi-navy">Décision du comité</h3>
            <div class="flex flex-wrap gap-2">
                <label v-for="decision in decisions" :key="decision.value" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm" :class="decisionForm.decision === decision.value ? 'border-hsi-gold bg-hsi-gold/10 text-hsi-navy' : 'border-hsi-border bg-white text-hsi-slate'">
                    <input v-model="decisionForm.decision" type="radio" :value="decision.value" class="h-4 w-4 text-hsi-navy"> {{ decision.label }}
                </label>
            </div>
            <p v-if="decisionForm.decision === 'accept' && blockingOpen.length" class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900">
                Critères bloquants à valider avant d’accepter : {{ blockingOpen.map((c) => c.label).join(', ') }}.
            </p>
            <textarea v-model="decisionForm.reason" rows="3" required minlength="5" class="w-full rounded-xl border border-hsi-border px-3 py-2 text-sm" placeholder="Motif de la décision (envoyé au demandeur)" />
            <p v-if="decisionForm.errors.reason || decisionForm.errors.decision" class="text-sm text-hsi-danger">{{ decisionForm.errors.reason || decisionForm.errors.decision }}</p>
            <HsiButton type="submit" :variant="decisionForm.decision === 'refuse' ? 'danger' : 'primary'" :disabled="decisionForm.processing || decisionForm.reason.length < 5 || (decisionForm.decision === 'accept' && blockingOpen.length > 0)">
                Enregistrer la décision
            </HsiButton>
        </form>
    </HsiCard>
</template>
