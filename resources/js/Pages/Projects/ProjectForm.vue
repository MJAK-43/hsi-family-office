<script setup>
import { computed } from 'vue';
import { Landmark, ListChecks, Paperclip, Plus, Trash2 } from 'lucide-vue-next';
import HsiSearchSelect from '@/Components/Hsi/HsiSearchSelect.vue';

const props = defineProps({
    form: { type: Object, required: true },
    countries: { type: Array, default: () => [] },
    sectors: { type: Array, default: () => [] },
    projectTypes: { type: Array, default: () => [] },
    refusedProjects: { type: Array, default: () => [] },
    loanRules: { type: Object, default: () => ({}) },
    isEdit: { type: Boolean, default: false },
    documentTypes: { type: Array, default: () => [] },
    fundAccounts: { type: Array, default: () => [] },
});

const emit = defineEmits(['submit']);

const inputClass = 'w-full rounded-xl border border-hsi-border bg-white px-4 py-2.5 text-sm text-hsi-navy focus:border-hsi-gold focus:outline-none focus:ring-2 focus:ring-hsi-gold/20';
const labelClass = 'block text-sm font-medium text-hsi-slate mb-1.5';

const selectedType = computed(() => props.projectTypes.find((type) => type.id === Number(props.form.project_type_id)) ?? null);
const isLoan = computed(() => !!selectedType.value?.is_loan);
const needsContribution = computed(() => !selectedType.value || selectedType.value.requires_personal_contribution);

const percent = (value) => {
    const total = parseFloat(props.form.total_budget) || 0;
    return total > 0 ? (((parseFloat(value) || 0) / total) * 100).toFixed(1) : '0';
};

// Mensualité indicative au taux annuel du règlement.
const monthly = computed(() => {
    const amount = parseFloat(props.form.requested_amount) || 0;
    const months = parseInt(props.form.loan_duration_months, 10) || 0;
    const rate = (props.loanRules.rate ?? 0) / 100 / 12;
    if (!amount || !months) return null;
    const payment = rate > 0 ? (amount * rate) / (1 - (1 + rate) ** -months) : amount / months;
    return { payment: payment.toFixed(2), total: (payment * months).toFixed(2) };
});

const today = new Date().toISOString().slice(0, 10);

// Listes avec recherche.
const countryOptions = computed(() => props.countries.map((c) => ({ value: c.id, label: `${c.flag_emoji ?? ''} ${c.name}`.trim() })));
const fundAccountOptions = computed(() => props.fundAccounts.map((a) => ({ value: a.id, label: a.name, hint: a.description ? `— ${a.description}` : '' })));
const sectorOptions = computed(() => props.sectors.map((s) => ({ value: s.value, label: s.label })));
const refusedOptions = computed(() => props.refusedProjects.map((p) => ({ value: p.id, label: `Oui : « ${p.title} »` })));

// Pièces jointes envoyées avec le formulaire (business plan, devis, pièce d'identité…).
const addAttachment = () => props.form.attachments.push({ file: null, type: isLoan.value ? 'identity_document' : 'business_plan', title: '' });
const removeAttachment = (index) => props.form.attachments.splice(index, 1);
const attachmentError = (index, field) => props.form.errors[`attachments.${index}.${field}`];
const fileSize = (file) => (file ? (file.size < 1048576 ? `${Math.max(1, Math.round(file.size / 1024))} Ko` : `${(file.size / 1048576).toFixed(1)} Mo`) : '');
</script>

<template>
    <form class="space-y-8" @submit.prevent="emit('submit')">
        <div>
            <h3 class="font-heading text-lg font-semibold text-hsi-navy mb-4">Type de demande</h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <label
                    v-for="type in projectTypes"
                    :key="type.id"
                    class="flex cursor-pointer gap-3 rounded-2xl border p-4 transition"
                    :class="Number(form.project_type_id) === type.id ? 'border-hsi-gold bg-hsi-gold/10' : 'border-hsi-border hover:border-hsi-gold/50'"
                >
                    <input v-model="form.project_type_id" type="radio" :value="type.id" class="mt-1 h-4 w-4 border-hsi-border text-hsi-navy" required>
                    <span>
                        <span class="flex items-center gap-2 font-semibold text-hsi-navy">
                            <Landmark v-if="type.is_loan" class="h-4 w-4 text-[#A8841F]" aria-hidden="true" />{{ type.name }}
                        </span>
                        <span v-if="type.description" class="mt-1 block text-sm text-hsi-slate">{{ type.description }}</span>
                    </span>
                </label>
            </div>
            <p v-if="form.errors.project_type_id" class="mt-1 text-xs text-hsi-danger">{{ form.errors.project_type_id }}</p>

            <div v-if="selectedType?.criteria?.length" class="mt-4 rounded-2xl border border-hsi-border bg-hsi-muted/40 p-4 text-sm">
                <p class="flex items-center gap-2 font-medium text-hsi-navy"><ListChecks class="h-4 w-4" aria-hidden="true" /> Le comité vérifiera :</p>
                <ul class="mt-2 grid gap-1 text-hsi-slate md:grid-cols-2">
                    <li v-for="criterion in selectedType.criteria" :key="criterion" class="flex gap-2"><span class="text-hsi-gold">•</span>{{ criterion }}</li>
                </ul>
            </div>

            <div v-if="refusedProjects.length" class="mt-4">
                <label :class="labelClass">S’agit-il d’un projet déjà refusé ?</label>
                <HsiSearchSelect v-model="form.resubmission_of_id" :options="refusedOptions" placeholder="Non, c’est un nouveau projet" clearable />
                <p class="mt-1 text-xs text-hsi-slate-light">Même projet : nouveau dépôt possible 3 mois après le refus. Autre projet : 30 jours après le dernier refus.</p>
                <p v-if="form.errors.resubmission_of_id" class="mt-1 text-xs text-hsi-danger">{{ form.errors.resubmission_of_id }}</p>
            </div>
        </div>

        <div>
            <h3 class="font-heading text-lg font-semibold text-hsi-navy mb-1">Compte de fonds *</h3>
            <p class="mb-3 text-sm text-hsi-slate">Le compte du fonds familial qui versera l’argent si le projet est accepté.</p>
            <HsiSearchSelect v-model="form.fund_account_id" :options="fundAccountOptions" placeholder="Choisir le compte de fonds" search-placeholder="Rechercher un compte…" required />
            <p v-if="form.errors.fund_account_id" class="mt-1 text-xs text-hsi-danger">{{ form.errors.fund_account_id }}</p>
        </div>

        <div>
            <h3 class="font-heading text-lg font-semibold text-hsi-navy mb-4">Informations générales</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label :class="labelClass">Titre du projet *</label>
                    <input v-model="form.title" type="text" :class="inputClass" maxlength="180" required>
                    <p v-if="form.errors.title" class="mt-1 text-xs text-hsi-danger">{{ form.errors.title }}</p>
                </div>
                <div class="md:col-span-2">
                    <label :class="labelClass">Résumé court</label>
                    <input v-model="form.short_description" type="text" :class="inputClass" maxlength="255">
                </div>
                <div class="md:col-span-2">
                    <label :class="labelClass">{{ isLoan ? 'Nature du projet : à quoi servira l’argent ?' : 'Description détaillée' }}</label>
                    <textarea v-model="form.description" rows="5" :class="inputClass" />
                </div>
                <div v-if="!isLoan">
                    <label :class="labelClass">Secteur *</label>
                    <HsiSearchSelect v-model="form.sector" :options="sectorOptions" placeholder="Sélectionner un secteur" required />
                    <p v-if="form.errors.sector" class="mt-1 text-xs text-hsi-danger">{{ form.errors.sector }}</p>
                </div>
                <div>
                    <label :class="labelClass">Pays</label>
                    <HsiSearchSelect v-model="form.country_id" :options="countryOptions" placeholder="Non précisé" search-placeholder="Rechercher un pays…" clearable />
                </div>
                <div>
                    <label :class="labelClass">Ville</label>
                    <input v-model="form.city" type="text" :class="inputClass" maxlength="120">
                </div>
            </div>
        </div>

        <!-- Prêt -->
        <div v-if="isLoan">
            <h3 class="font-heading text-lg font-semibold text-hsi-navy mb-2">Prêt demandé</h3>
            <p class="mb-4 rounded-xl border border-hsi-gold/30 bg-hsi-gold/5 p-3 text-sm text-hsi-slate">
                Taux annuel {{ loanRules.rate }} % · remboursement en {{ loanRules.max_duration }} mois maximum · plafond actuel {{ loanRules.fund_cap }} ({{ loanRules.cap_percent }} % du solde du fonds).
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label :class="labelClass">Montant demandé ($) *</label>
                    <input v-model="form.requested_amount" type="number" step="0.01" min="1" :class="inputClass" required>
                    <p v-if="form.errors.requested_amount" class="mt-1 text-xs text-hsi-danger">{{ form.errors.requested_amount }}</p>
                </div>
                <div>
                    <label :class="labelClass">Durée de remboursement (mois) *</label>
                    <input v-model="form.loan_duration_months" type="number" min="1" :max="loanRules.max_duration" :class="inputClass" required>
                    <p v-if="form.errors.loan_duration_months" class="mt-1 text-xs text-hsi-danger">{{ form.errors.loan_duration_months }}</p>
                    <p v-else-if="monthly" class="mt-1 text-xs text-hsi-slate-light">Environ {{ monthly.payment }} $ par mois, {{ monthly.total }} $ au total (indicatif).</p>
                </div>
                <div class="md:col-span-2">
                    <label :class="labelClass">Plan de remboursement proposé *</label>
                    <textarea v-model="form.repayment_plan" rows="3" :class="inputClass" required placeholder="Ex. : 12 mensualités prélevées sur mon salaire à partir de mars." />
                    <p v-if="form.errors.repayment_plan" class="mt-1 text-xs text-hsi-danger">{{ form.errors.repayment_plan }}</p>
                </div>
                <div>
                    <label :class="labelClass">Date prévue de présentation au groupe (appel vidéo) *</label>
                    <input v-model="form.presentation_date" type="date" :min="today" :class="inputClass" required>
                    <p class="mt-1 text-xs text-hsi-slate-light">Prévenez le groupe entre {{ loanRules.notice_min }} et {{ loanRules.notice_max }} jours à l’avance.</p>
                    <p v-if="form.errors.presentation_date" class="mt-1 text-xs text-hsi-danger">{{ form.errors.presentation_date }}</p>
                </div>
            </div>
        </div>

        <!-- Projet cofinancé -->
        <div v-else>
            <h3 class="font-heading text-lg font-semibold text-hsi-navy mb-2">Budget et financement</h3>
            <p v-if="needsContribution" class="mb-4 rounded-xl border border-hsi-gold/30 bg-hsi-gold/5 p-3 text-sm text-hsi-slate">
                Le porteur apporte au minimum <strong>50 %</strong> du budget total. HSI finance au maximum <strong>50 %</strong>.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label :class="labelClass">Budget total ($) *</label>
                    <input v-model="form.total_budget" type="number" step="0.01" min="1" :class="inputClass" required>
                </div>
                <div>
                    <label :class="labelClass">Apport personnel ($) * ({{ percent(form.personal_contribution) }} %)</label>
                    <input v-model="form.personal_contribution" type="number" step="0.01" min="0" :class="inputClass" required>
                    <p v-if="form.errors.personal_contribution" class="mt-1 text-xs text-hsi-danger">{{ form.errors.personal_contribution }}</p>
                </div>
                <div>
                    <label :class="labelClass">Montant demandé à HSI ($) * ({{ percent(form.requested_amount) }} %)</label>
                    <input v-model="form.requested_amount" type="number" step="0.01" min="0" :class="inputClass" required>
                    <p v-if="form.errors.requested_amount" class="mt-1 text-xs text-hsi-danger">{{ form.errors.requested_amount }}</p>
                </div>
                <div>
                    <label :class="labelClass">Durée prévue (mois)</label>
                    <input v-model="form.expected_duration_months" type="number" min="1" max="240" :class="inputClass">
                </div>
                <div>
                    <label :class="labelClass">ROI attendu (%)</label>
                    <input v-model="form.expected_roi_percentage" type="number" step="0.01" min="0" max="100" :class="inputClass">
                </div>
            </div>
        </div>

        <!-- Pièces jointes -->
        <div v-if="form.attachments">
            <h3 class="mb-1 flex items-center gap-2 font-heading text-lg font-semibold text-hsi-navy"><Paperclip class="h-5 w-5 text-hsi-gold" aria-hidden="true" /> Pièces jointes</h3>
            <p class="mb-4 text-sm text-hsi-slate">Business plan, devis, pièce d’identité, justificatifs… (PDF, images, Word, Excel — 10 Mo maximum par fichier). Vous pourrez en ajouter d’autres depuis la fiche du projet.</p>
            <ul v-if="form.attachments.length" class="mb-3 space-y-3">
                <li v-for="(attachment, index) in form.attachments" :key="index" class="grid gap-3 rounded-2xl border border-hsi-border p-3 md:grid-cols-[1fr_12rem_1fr_auto] md:items-start">
                    <div>
                        <input :id="`attachment-file-${index}`" type="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" class="w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-hsi-muted file:px-3 file:py-2 file:text-sm file:font-medium file:text-hsi-navy" @change="attachment.file = $event.target.files[0] ?? null">
                        <p v-if="attachment.file" class="mt-1 text-xs text-hsi-slate-light">{{ fileSize(attachment.file) }}</p>
                        <p v-if="attachmentError(index, 'file')" class="mt-1 text-xs text-hsi-danger">{{ attachmentError(index, 'file') }}</p>
                    </div>
                    <select v-model="attachment.type" :class="inputClass" :aria-label="$tr('Type de pièce')">
                        <option v-for="t in documentTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <input v-model="attachment.title" type="text" maxlength="150" :class="inputClass" :placeholder="$tr('Titre (facultatif)')">
                    <button type="button" class="justify-self-end rounded-lg p-2 text-hsi-danger hover:bg-red-50" :aria-label="$tr('Retirer')" @click="removeAttachment(index)"><Trash2 class="h-4 w-4" /></button>
                </li>
            </ul>
            <button v-if="form.attachments.length < 10" type="button" class="inline-flex items-center gap-2 rounded-xl border border-dashed border-hsi-border px-4 py-2.5 text-sm font-medium text-hsi-navy hover:border-hsi-gold hover:bg-hsi-gold/5" @click="addAttachment">
                <Plus class="h-4 w-4" aria-hidden="true" /> Ajouter une pièce jointe
            </button>
        </div>

        <div class="flex flex-wrap gap-3 pt-4 border-t border-hsi-border">
            <slot name="cancel" />
            <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-xl bg-hsi-navy px-5 py-2.5 text-sm font-medium text-white hover:bg-hsi-navy-soft transition-colors disabled:opacity-50"
                :disabled="form.processing || !form.project_type_id"
            >
                {{ isEdit ? 'Enregistrer' : 'Créer le brouillon' }}
            </button>
        </div>
    </form>
</template>
