<script setup>
import { tr } from '@/i18n';
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Pencil, Send, Check, Ban, Vote, Trash2, Download, FileText, Upload,
    CheckCircle, XCircle, AlertTriangle, Wallet, Play, Flag,
} from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';
import ApplicantSituation from '@/Components/Projects/ApplicantSituation.vue';
import CriteriaChecklist from '@/Components/Projects/CriteriaChecklist.vue';
import VotePanel from '@/Components/Projects/VotePanel.vue';
import LoanPanel from '@/Components/Projects/LoanPanel.vue';
import ApprovalCard from '@/Components/Approvals/ApprovalCard.vue';

const props = defineProps({
    project: Object,
    activities: Array,
    can: Object,
    statuses: Array,
    situation: { type: Object, default: null },
    checks: { type: Object, default: null },
    criterionStatuses: { type: Array, default: () => [] },
    decisions: { type: Array, default: () => [] },
    loan: { type: Object, default: null },
    approvals: { type: Array, default: () => [] },
    voteDefaults: { type: Object, default: () => ({}) },
    disbursementDefaults: { type: Object, default: () => ({}) },
});
const showCommitteeSection = ['submitted', 'under_review', 'needs_information', 'approved_by_committee', 'rejected_by_committee', 'submitted_to_vote', 'approved_by_family', 'rejected_by_family', 'funded', 'in_progress', 'completed'];

const showRejectModal = ref(false);
const showEvaluateModal = ref(false);
const rejectedReason = ref('');
const approveComment = ref('');
const fundingIdempotencyKey = ref(crypto.randomUUID());

const evaluateForm = useForm({
    profitability_score: null,
    risk_score: null,
    founder_score: null,
    family_impact_score: null,
    sustainability_score: null,
    values_alignment_score: null,
    recommendation: '',
    comment: '',
});

const documentForm = useForm({
    type: 'business_plan',
    title: '',
    document: null,
    visibility: 'all',
});

const milestoneForm = useForm({ title: '', description: '', due_date: '', planned_amount: '' });
const reportForm = useForm({ progress_percent: 10, spent_amount: 0, summary: '', risks: '', next_steps: '', reported_at: new Date().toISOString().slice(0, 10) });

const scoreCriteria = [
    { key: 'profitability_score', label: 'Rentabilité' },
    { key: 'risk_score', label: 'Risque' },
    { key: 'founder_score', label: 'Sérieux du porteur' },
    { key: 'family_impact_score', label: 'Impact familial' },
    { key: 'sustainability_score', label: 'Durabilité' },
    { key: 'values_alignment_score', label: 'Alignement valeurs HSI' },
];

const submitProject = () => {
    if (confirm(tr('Soumettre ce projet pour analyse ?'))) {
        router.patch(route('projects.submit', props.project.id));
    }
};

const submitEvaluate = () => {
    evaluateForm.post(route('projects.evaluate', props.project.id), {
        onSuccess: () => { showEvaluateModal.value = false; },
    });
};

const approveProject = () => {
    if (confirm(tr('Approuver ce projet par le comité ?'))) {
        router.patch(route('projects.approve-by-committee', props.project.id), {
            committee_decision_comment: approveComment.value || undefined,
        });
    }
};

const submitReject = () => {
    if (rejectedReason.value.length < 5) return;
    router.patch(route('projects.reject-by-committee', props.project.id), {
        rejected_reason: rejectedReason.value,
    }, {
        onSuccess: () => {
            showRejectModal.value = false;
            rejectedReason.value = '';
        },
    });
};

const submitToVote = () => {
    if (confirm(tr('Soumettre ce projet au vote familial ?'))) {
        router.patch(route('projects.submit-to-vote', props.project.id));
    }
};

const fundProject = () => {
    if (confirm(tr('Financer ce projet pour {amount} depuis la poche Projets familiaux ?', { amount: props.project.requested_amount }))) {
        router.patch(route('projects.fund', props.project.id), {
            idempotency_key: fundingIdempotencyKey.value,
        }, {
            onSuccess: () => { fundingIdempotencyKey.value = crypto.randomUUID(); },
        });
    }
};

const startProject = () => {
    if (confirm('Démarrer l’exécution de ce projet ?')) router.patch(route('projects.start', props.project.id));
};
const closeProject = (action) => {
    const label = action === 'complete' ? 'Bilan de clôture' : action === 'fail' ? 'Motif de la déclaration d’échec' : 'Motif de l’annulation';
    const note = prompt(`${tr(label)} ${tr('(minimum 5 caractères) :')}`);
    if (note?.length >= 5) router.patch(route(`projects.${action === 'cancel' ? 'cancel-execution' : action}`, props.project.id), { closure_note: note });
};
const addMilestone = () => milestoneForm.post(route('projects.milestones.store', props.project.id), { onSuccess: () => milestoneForm.reset() });
const updateMilestone = (milestone, status) => router.patch(route('projects.milestones.update', milestone.id), { status, spent_amount: milestone.spent_amount });
const addReport = () => reportForm.post(route('projects.progress-reports.store', props.project.id), { onSuccess: () => reportForm.reset('summary', 'risks', 'next_steps') });

const destroyProject = () => {
    if (confirm(tr('Supprimer ce projet ?'))) {
        router.delete(route('projects.destroy', props.project.id));
    }
};

const uploadDocument = () => {
    documentForm.post(route('projects.documents.store', props.project.id), {
        forceFormData: true,
        onSuccess: () => documentForm.reset('document', 'title'),
    });
};

const deleteDocument = (docId) => {
    if (confirm(tr('Supprimer ce document ?'))) {
        router.delete(route('projects.documents.destroy', docId));
    }
};

const formatFileSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1048576) return tr('{n} Ko', { n: Math.round(bytes / 1024) });
    return tr('{n} Mo', { n: (bytes / 1048576).toFixed(1) });
};

const onDocumentChange = (event) => {
    documentForm.document = event.target.files[0] ?? null;
};
</script>

<template>
    <Head :title="project.title" />

    <AuthenticatedLayout>
        <HsiPageHeader
            :title="project.title"
            :subtitle="project.short_description || (project.type?.is_loan ? `${$tr(project.type.name)} — ${project.requested_amount}` : `${$tr(project.sector)} — ${project.country}`)"
        >
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="can.edit"
                        :href="route('projects.edit', project.id)"
                    >
                        <HsiButton variant="outline">
                            <Pencil class="h-4 w-4" />
                            Modifier
                        </HsiButton>
                    </Link>
                    <HsiButton
                        v-if="can.submit"
                        @click="submitProject"
                    >
                        <Send class="h-4 w-4" />
                        {{ project.status === 'needs_information' ? 'Redéposer le dossier' : 'Déposer la demande' }}
                    </HsiButton>
                    <HsiButton
                        v-if="can.evaluate"
                        variant="outline"
                        @click="showEvaluateModal = true"
                    >
                        Évaluer
                    </HsiButton>

                    <HsiButton v-if="can.start" @click="startProject">
                        <Play class="h-4 w-4" /> Démarrer
                    </HsiButton>
                    <template v-if="can.manageExecution && project.status === 'in_progress'">
                        <HsiButton @click="closeProject('complete')"><Flag class="h-4 w-4" /> Terminer</HsiButton>
                        <HsiButton variant="danger" @click="closeProject('fail')">Échec</HsiButton>
                        <HsiButton variant="outline" @click="closeProject('cancel')">Annuler l’exécution</HsiButton>
                    </template>
                    <Link
                        v-if="project.active_vote_id"
                        :href="route('votes.show', project.active_vote_id)"
                    >
                        <HsiButton variant="outline">
                            <Vote class="h-4 w-4" />
                            Voir le vote
                        </HsiButton>
                    </Link>
                    <HsiButton
                        v-if="can.delete"
                        variant="danger"
                        @click="destroyProject"
                    >
                        <Trash2 class="h-4 w-4" />
                    </HsiButton>
                </div>
            </template>
        </HsiPageHeader>

        <div class="mb-6 flex flex-wrap items-center gap-3">
            <HsiBadge
                :variant="project.status_variant"
                size="lg"
            >
                {{ project.status_label }}
            </HsiBadge>
            <HsiBadge v-if="project.type" variant="gold" size="lg">{{ project.type.name }}</HsiBadge>
            <span class="text-sm text-hsi-slate">Porteur : <strong>{{ project.owner }}</strong></span>
            <span v-if="project.fund_account" class="text-sm text-hsi-slate">Compte de fonds : <strong>{{ project.fund_account }}</strong></span>
            <HsiBadge v-if="project.is_example" variant="neutral" size="lg">Exemple</HsiBadge>
            <span v-if="project.resubmission_of" class="text-sm text-hsi-slate">Nouveau dépôt de « {{ project.resubmission_of }} »</span>
            <span
                v-if="project.city"
                class="text-sm text-hsi-slate"
            >{{ project.city }}, {{ project.country }}</span>
        </div>

        <p v-if="$page.props.errors?.submit" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $page.props.errors.submit }}</p>

        <!-- Réponse du comité au demandeur -->
        <div v-if="project.status === 'needs_information' && project.information_request" class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">Compléments demandés par le comité le {{ project.decided_at }}</p>
            <p class="mt-1 whitespace-pre-wrap">{{ project.information_request }}</p>
            <p class="mt-2">Complétez votre dossier (modification, pièces jointes) puis cliquez sur « Redéposer le dossier ».</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <ApplicantSituation v-if="situation && showCommitteeSection.includes(project.status)" :situation="situation" :checks="checks" />

                <CriteriaChecklist
                    v-if="project.criteria?.length || can.review"
                    :project="project"
                    :criteria="project.criteria ?? []"
                    :statuses="criterionStatuses"
                    :decisions="decisions"
                    :can-review="can.review"
                />

                <VotePanel
                    v-if="can.submitToVote || project.active_vote_id"
                    :project="project"
                    :defaults="voteDefaults"
                    :can-launch="can.submitToVote"
                    :can-reschedule="can.rescheduleVote"
                />

                <LoanPanel
                    v-if="can.fund || loan"
                    :project="project"
                    :loan="loan"
                    :defaults="disbursementDefaults"
                    :can-fund="can.fund"
                    :can-record-repayment="can.recordRepayment"
                />

                <HsiCard v-if="approvals.length" padding="lg">
                    <HsiSectionTitle title="Validations des trésoriers" />
                    <div class="space-y-3">
                        <ApprovalCard v-for="request in approvals" :key="request.id" :request="request" />
                    </div>
                </HsiCard>

                <HsiCard padding="lg">
                    <HsiSectionTitle title="Financement" />
                    <div v-if="!project.type?.is_loan" class="rounded-xl border border-hsi-gold/30 bg-hsi-gold/5 p-4 mb-4 text-sm">
                        HSI finance maximum 50 % du budget total.
                    </div>
                    <dl v-if="project.type?.is_loan" class="mb-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="text-hsi-slate-light">Durée de remboursement demandée</dt><dd class="font-semibold text-hsi-navy">{{ project.loan_duration_months }} mois</dd></div>
                        <div><dt class="text-hsi-slate-light">Présentation au groupe</dt><dd class="font-semibold text-hsi-navy">{{ project.presentation_date || '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-hsi-slate-light">Plan de remboursement proposé</dt><dd class="whitespace-pre-wrap text-hsi-navy">{{ project.repayment_plan }}</dd></div>
                    </dl>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-hsi-slate-light">Budget total</dt>
                            <dd class="text-lg font-semibold text-hsi-navy">{{ project.total_budget }}</dd>
                        </div>
                        <div>
                            <dt class="text-hsi-slate-light">Apport personnel ({{ project.personal_percent }} %)</dt>
                            <dd class="text-lg font-semibold text-hsi-navy">{{ project.personal_contribution }}</dd>
                        </div>
                        <div>
                            <dt class="text-hsi-slate-light">Montant demandé HSI ({{ project.requested_percent }} %)</dt>
                            <dd class="text-lg font-semibold text-hsi-navy">{{ project.requested_amount }}</dd>
                        </div>
                        <div v-if="project.funded_amount">
                            <dt class="text-hsi-slate-light">Montant financé</dt>
                            <dd class="text-lg font-semibold text-hsi-navy">{{ project.funded_amount }}</dd>
                            <p
                                v-if="project.funded_at"
                                class="text-xs text-hsi-slate-light mt-1"
                            >
                                Le {{ project.funded_at }}
                                <span v-if="project.funded_by"> par {{ project.funded_by }}</span>
                            </p>
                        </div>
                        <div v-if="!project.type?.is_loan">
                            <dt class="text-hsi-slate-light">Règle 50/50</dt>
                            <dd class="flex items-center gap-2 mt-1">
                                <CheckCircle
                                    v-if="project.respects_fifty_fifty"
                                    class="h-5 w-5 text-hsi-success"
                                />
                                <XCircle
                                    v-else
                                    class="h-5 w-5 text-hsi-danger"
                                />
                                <span :class="project.respects_fifty_fifty ? 'text-hsi-success' : 'text-hsi-danger'">
                                    {{ project.respects_fifty_fifty ? 'Respectée' : 'Non respectée' }}
                                </span>
                            </dd>
                        </div>
                        <div v-if="project.expected_duration_months">
                            <dt class="text-hsi-slate-light">Durée prévue</dt>
                            <dd>{{ project.expected_duration_months }} mois</dd>
                        </div>
                        <div v-if="project.expected_roi_percentage">
                            <dt class="text-hsi-slate-light">ROI attendu</dt>
                            <dd>{{ project.expected_roi_percentage }} %</dd>
                        </div>
                    </dl>
                </HsiCard>

                <HsiCard
                    v-if="project.description"
                    padding="lg"
                >
                    <HsiSectionTitle title="Description" />
                    <p class="text-sm text-hsi-slate whitespace-pre-wrap">{{ project.description }}</p>
                </HsiCard>

                <HsiCard v-if="['funded', 'in_progress', 'completed', 'failed', 'cancelled'].includes(project.status)" padding="lg">
                    <HsiSectionTitle title="Exécution et jalons" />
                    <div class="mb-4">
                        <div class="flex justify-between text-sm mb-1"><span>Avancement</span><strong>{{ project.progress_percent }} %</strong></div>
                        <div class="h-2 rounded-full bg-hsi-muted"><div class="h-2 rounded-full bg-hsi-gold" :style="{ width: `${project.progress_percent}%` }" /></div>
                        <p v-if="project.closure_note" class="mt-3 text-sm text-hsi-slate">{{ project.closure_note }}</p>
                    </div>
                    <div v-if="project.milestones?.length" class="space-y-2 mb-5">
                        <div v-for="milestone in project.milestones" :key="milestone.id" class="rounded-xl border border-hsi-border p-3 flex gap-3 items-center justify-between">
                            <div><p class="font-medium text-sm">{{ milestone.title }}</p><p class="text-xs text-hsi-slate-light">{{ milestone.due_date || 'Sans date' }} · {{ milestone.status_label }}</p></div>
                            <select v-if="can.reportProgress" :value="milestone.status" class="rounded-lg border-hsi-border text-xs" @change="updateMilestone(milestone, $event.target.value)">
                                <option value="pending">À faire</option><option value="in_progress">En cours</option><option value="completed">Terminé</option><option value="blocked">Bloqué</option>
                            </select>
                        </div>
                    </div>
                    <form v-if="can.manageExecution" class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-6" @submit.prevent="addMilestone">
                        <input v-model="milestoneForm.title" required placeholder="Nouveau jalon" class="rounded-xl border-hsi-border text-sm" />
                        <input v-model="milestoneForm.due_date" type="date" class="rounded-xl border-hsi-border text-sm" />
                        <input v-model="milestoneForm.planned_amount" type="number" min="0" step="0.01" placeholder="Budget prévu" class="rounded-xl border-hsi-border text-sm" />
                        <HsiButton type="submit" :disabled="milestoneForm.processing">Ajouter</HsiButton>
                    </form>
                    <form v-if="can.reportProgress" class="space-y-3 border-t border-hsi-border pt-4" @submit.prevent="addReport">
                        <h3 class="font-medium text-sm">Nouveau rapport d’avancement</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                            <input v-model="reportForm.progress_percent" type="number" min="1" max="99" required class="rounded-xl border-hsi-border text-sm" placeholder="Avancement %" />
                            <input v-model="reportForm.spent_amount" type="number" min="0" step="0.01" required class="rounded-xl border-hsi-border text-sm" placeholder="Montant utilisé" />
                            <input v-model="reportForm.reported_at" type="date" required class="rounded-xl border-hsi-border text-sm" />
                        </div>
                        <textarea v-model="reportForm.summary" required minlength="10" rows="3" class="w-full rounded-xl border-hsi-border text-sm" placeholder="Résumé des réalisations" />
                        <textarea v-model="reportForm.risks" rows="2" class="w-full rounded-xl border-hsi-border text-sm" placeholder="Risques ou blocages" />
                        <textarea v-model="reportForm.next_steps" rows="2" class="w-full rounded-xl border-hsi-border text-sm" placeholder="Prochaines étapes" />
                        <HsiButton type="submit" :disabled="reportForm.processing">Publier le rapport</HsiButton>
                    </form>
                    <div v-if="project.progress_reports?.length" class="mt-5 space-y-3 border-t border-hsi-border pt-4">
                        <article v-for="report in project.progress_reports" :key="report.id" class="rounded-xl bg-hsi-muted/50 p-3 text-sm">
                            <div class="flex justify-between"><strong>{{ report.progress_percent }} % — {{ report.reported_at }}</strong><span>{{ report.reported_by }}</span></div>
                            <p class="mt-2 whitespace-pre-wrap">{{ report.summary }}</p>
                        </article>
                    </div>
                </HsiCard>

                <HsiCard padding="lg">
                    <HsiSectionTitle title="Documents" />
                    <div
                        v-if="project.documents?.length"
                        class="space-y-2 mb-4"
                    >
                        <div
                            v-for="doc in project.documents"
                            :key="doc.id"
                            class="flex items-center justify-between rounded-xl border border-hsi-border p-3"
                        >
                            <div class="flex items-center gap-3">
                                <FileText class="h-5 w-5 text-hsi-gold" />
                                <div>
                                    <p class="text-sm font-medium">{{ doc.title }} <span v-if="doc.visibility === 'committee'" class="ml-1 rounded-full bg-hsi-navy/5 px-2 py-0.5 text-[11px] font-semibold text-hsi-navy">Interne au comité</span></p>
                                    <p class="text-xs text-hsi-slate-light">
                                        {{ doc.type }} — {{ formatFileSize(doc.file_size) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <a
                                    v-if="can.downloadDocument"
                                    :href="doc.download_url"
                                    class="text-hsi-gold hover:text-hsi-gold-light"
                                >
                                    <Download class="h-4 w-4" />
                                </a>
                                <button
                                    v-if="can.uploadDocument"
                                    class="text-hsi-danger"
                                    @click="deleteDocument(doc.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <p
                        v-else
                        class="text-sm text-hsi-slate-light mb-4"
                    >
                        Aucun document joint.
                    </p>

                    <form
                        v-if="can.uploadDocument"
                        class="border-t border-hsi-border pt-4 space-y-3"
                        @submit.prevent="uploadDocument"
                    >
                        <p class="text-sm font-medium text-hsi-navy">Ajouter un document</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <select
                                v-model="documentForm.type"
                                class="rounded-xl border border-hsi-border px-3 py-2 text-sm"
                                required
                            >
                                <option value="business_plan">Business plan</option>
                                <option value="financial_forecast">Prévisions financières</option>
                                <option value="identity_document">Pièce d'identité</option>
                                <option value="quote">Devis</option>
                                <option value="invoice">Facture</option>
                                <option value="presentation">Présentation</option>
                                <option value="legal_document">Document juridique</option>
                                <option value="other">Autre</option>
                            </select>
                            <input
                                v-model="documentForm.title"
                                type="text"
                                placeholder="Titre (optionnel)"
                                class="rounded-xl border border-hsi-border px-3 py-2 text-sm"
                            />
                            <select v-if="can.seeInternal" v-model="documentForm.visibility" class="rounded-xl border border-hsi-border px-3 py-2 text-sm sm:col-span-2">
                                <option value="all">Visible par le demandeur</option>
                                <option value="committee">Interne au comité et aux trésoriers</option>
                            </select>
                        </div>
                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                            class="text-sm"
                            required
                            @change="onDocumentChange"
                        />
                        <HsiButton
                            type="submit"
                            size="sm"
                            :disabled="documentForm.processing"
                        >
                            <Upload class="h-4 w-4" />
                            Téléverser
                        </HsiButton>
                    </form>
                </HsiCard>

                <HsiCard
                    v-if="project.evaluations?.length"
                    padding="lg"
                >
                    <HsiSectionTitle :title="$tr('Évaluations comité (score moyen : {score}/120)', { score: project.committee_score ?? '—' })" />
                    <div class="space-y-4">
                        <div
                            v-for="evalItem in project.evaluations"
                            :key="evalItem.id"
                            class="rounded-xl border border-hsi-border p-4"
                        >
                            <div class="flex justify-between mb-2">
                                <span class="font-medium text-sm">{{ evalItem.evaluator }}</span>
                                <span class="font-bold text-hsi-gold">{{ evalItem.total_score }}/120</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs text-hsi-slate">
                                <span>Rentabilité : {{ evalItem.profitability_score ?? '—' }}</span>
                                <span>Risque : {{ evalItem.risk_score ?? '—' }}</span>
                                <span>Porteur : {{ evalItem.founder_score ?? '—' }}</span>
                                <span>Impact : {{ evalItem.family_impact_score ?? '—' }}</span>
                                <span>Durabilité : {{ evalItem.sustainability_score ?? '—' }}</span>
                                <span>Valeurs : {{ evalItem.values_alignment_score ?? '—' }}</span>
                            </div>
                            <p
                                v-if="evalItem.comment"
                                class="mt-2 text-sm text-hsi-slate italic"
                            >
                                {{ evalItem.comment }}
                            </p>
                        </div>
                    </div>
                </HsiCard>

                <HsiCard
                    v-if="project.committee_decision_comment || project.rejected_reason"
                    padding="lg"
                >
                    <HsiSectionTitle title="Décision comité" />
                    <p
                        v-if="project.committee_decision_comment"
                        class="text-sm text-hsi-slate"
                    >
                        {{ project.committee_decision_comment }}
                    </p>
                    <p
                        v-if="project.rejected_reason"
                        class="text-sm text-hsi-danger"
                    >
                        <AlertTriangle class="inline h-4 w-4 mr-1" />
                        {{ project.rejected_reason }}
                    </p>
                </HsiCard>
            </div>

            <div class="space-y-6">
                <HsiCard padding="lg">
                    <HsiSectionTitle title="Étapes" />
                    <ol class="space-y-4">
                        <li
                            v-for="(step, i) in project.timeline"
                            :key="i"
                            class="flex gap-3"
                        >
                            <div
                                :class="[
                                    'h-2 w-2 mt-2 rounded-full shrink-0',
                                    step.status === 'done' ? 'bg-hsi-success' : step.status === 'current' ? 'bg-hsi-gold' : 'bg-hsi-muted',
                                ]"
                            />
                            <div>
                                <p class="text-sm font-medium text-hsi-navy">{{ step.label }}</p>
                                <p class="text-xs text-hsi-slate-light">{{ step.date }}</p>
                            </div>
                        </li>
                    </ol>
                </HsiCard>

                <HsiCard padding="lg">
                    <HsiSectionTitle title="Historique du dossier" />
                    <ul class="max-h-[640px] space-y-3 overflow-y-auto pr-1">
                        <li
                            v-for="activity in activities"
                            :key="activity.id"
                            class="text-sm border-b border-hsi-border/50 pb-2 last:border-0"
                        >
                            <p class="text-hsi-navy">{{ activity.description }}</p>
                            <p class="text-xs text-hsi-slate-light">
                                <template v-if="activity.causer">{{ activity.causer }} — </template>{{ activity.created_at }}
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

        <!-- Modal évaluation -->
        <div
            v-if="showEvaluateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-hsi-navy/50 p-4"
            @click.self="showEvaluateModal = false"
        >
            <div class="bg-white rounded-2xl p-6 max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <h3 class="font-heading text-lg font-semibold text-hsi-navy mb-4">
                    Évaluer le projet
                </h3>
                <form
                    class="space-y-4"
                    @submit.prevent="submitEvaluate"
                >
                    <div
                        v-for="criterion in scoreCriteria"
                        :key="criterion.key"
                    >
                        <label class="block text-sm font-medium text-hsi-slate mb-1">
                            {{ criterion.label }} (0-20)
                        </label>
                        <input
                            v-model="evaluateForm[criterion.key]"
                            type="number"
                            min="0"
                            max="20"
                            class="w-full rounded-xl border border-hsi-border px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-hsi-slate mb-1">Recommandation</label>
                        <input
                            v-model="evaluateForm.recommendation"
                            type="text"
                            class="w-full rounded-xl border border-hsi-border px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-hsi-slate mb-1">Commentaire</label>
                        <textarea
                            v-model="evaluateForm.comment"
                            rows="3"
                            class="w-full rounded-xl border border-hsi-border px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="flex gap-2 justify-end">
                        <HsiButton
                            variant="ghost"
                            type="button"
                            @click="showEvaluateModal = false"
                        >
                            Annuler
                        </HsiButton>
                        <HsiButton
                            type="submit"
                            :disabled="evaluateForm.processing"
                        >
                            Enregistrer
                        </HsiButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal refus -->
        <div
            v-if="showRejectModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-hsi-navy/50 p-4"
            @click.self="showRejectModal = false"
        >
            <div class="bg-white rounded-2xl p-6 max-w-md w-full">
                <h3 class="font-heading text-lg font-semibold text-hsi-navy mb-4">
                    Rejeter le projet
                </h3>
                <textarea
                    v-model="rejectedReason"
                    rows="4"
                    placeholder="Motif du refus (min. 5 caractères)"
                    class="w-full rounded-xl border border-hsi-border px-3 py-2 text-sm mb-4"
                />
                <div class="flex gap-2 justify-end">
                    <HsiButton
                        variant="ghost"
                        @click="showRejectModal = false"
                    >
                        Annuler
                    </HsiButton>
                    <HsiButton
                        variant="danger"
                        :disabled="rejectedReason.length < 5"
                        @click="submitReject"
                    >
                        Confirmer le refus
                    </HsiButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
