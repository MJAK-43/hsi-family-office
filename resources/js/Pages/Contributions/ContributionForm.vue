<script setup>
import { computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CalendarClock, HandCoins, Info, ShieldCheck } from 'lucide-vue-next';
import HsiSearchSelect from '@/Components/Hsi/HsiSearchSelect.vue';
import { formatMoney } from '@/currency';

/**
 * Déclaration d'une cotisation (section 4 du règlement) :
 * contribution trimestrielle fixe avant le 15 du 1er mois du trimestre, ou contribution volontaire en plus.
 */
const props = defineProps({
    form: { type: Object, required: true },
    plans: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    canSelectMember: { type: Boolean, default: false },
    isEdit: { type: Boolean, default: false },
    rules: { type: Object, default: () => ({}) },
    quarterDues: { type: Object, default: () => ({}) },
    treasurersByMember: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['submit']);
const page = usePage();

const inputClass = 'w-full rounded-xl border border-hsi-border bg-white px-4 py-2.5 text-sm text-hsi-navy focus:border-hsi-gold focus:outline-none focus:ring-2 focus:ring-hsi-gold/20';
const labelClass = 'block text-sm font-medium text-hsi-slate mb-1.5';
const money = (v) => formatMoney(v, { decimals: 2 });
const months = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
const quarters = [1, 2, 3, 4].map((q) => ({ value: q, label: `T${q}`, months: `${months[(q - 1) * 3]} – ${months[(q - 1) * 3 + 2]}` }));
const thisYear = new Date().getFullYear();
const years = [thisYear - 1, thisYear, thisYear + 1];

const memberOptions = computed(() => props.members.map((m) => ({ value: m.id, label: m.name, hint: [m.zone, m.email].filter(Boolean).join(' · ') })));
const memberId = computed(() => (props.canSelectMember ? props.form.user_id : page.props.auth.user.id));
const zoneInfo = computed(() => props.treasurersByMember[memberId.value] ?? null);

// Date limite et pénalité (section 4.3) : +X par période de 7 jours entamée à partir du 16.
const dueDate = computed(() => {
    if (!props.form.quarter || !props.form.payment_year) return null;
    const month = (props.form.quarter - 1) * 3;
    return new Date(Number(props.form.payment_year), month, Math.min(props.rules.due_day ?? 15, 28));
});
const fmtDate = (d) => d?.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
const penalty = computed(() => {
    if (!dueDate.value || !props.form.paid_at) return 0;
    const due = dueDate.value;
    let start = new Date(due.getFullYear(), due.getMonth(), Math.min(props.rules.penalty_start_day ?? 16, 28));
    if (start <= due) start = new Date(due.getFullYear(), due.getMonth(), due.getDate() + 1);
    const paid = new Date(`${props.form.paid_at}T00:00:00`);
    if (paid < start) return 0;
    const days = Math.floor((paid - start) / 86400000);
    return (Math.floor(days / (props.rules.penalty_period_days || 7)) + 1) * (props.rules.penalty_amount ?? 0);
});
const periodKey = computed(() => (props.form.quarter && props.form.payment_year ? `${props.form.payment_year}-Q${props.form.quarter}` : null));
const openDue = computed(() => (periodKey.value ? props.quarterDues?.[memberId.value]?.[periodKey.value] ?? null : null));
const carried = computed(() => openDue.value?.carried_penalty ?? 0);
const penaltyApplied = computed(() => (openDue.value?.penalty_waived ? 0 : penalty.value));
const expected = computed(() => (props.rules.quarterly_amount ?? 0) + penaltyApplied.value + carried.value);
const amountTooLow = computed(() => props.form.kind === 'quarterly' && Number(props.form.amount) > 0 && Number(props.form.amount) < (props.rules.quarterly_amount ?? 0));

// Le montant proposé suit le montant attendu tant que la personne ne l'a pas modifié.
let lastSuggested = null;
watch(expected, (value) => {
    if (props.form.kind !== 'quarterly') return;
    if (props.form.amount === '' || props.form.amount === null || Number(props.form.amount) === lastSuggested) {
        props.form.amount = Number(value.toFixed(2));
    }
    lastSuggested = Number(value.toFixed(2));
}, { immediate: true });
watch(() => props.form.kind, (kind) => {
    if (kind === 'voluntary' && Number(props.form.amount) === lastSuggested) props.form.amount = '';
    if (kind === 'quarterly') { props.form.amount = Number(expected.value.toFixed(2)); lastSuggested = props.form.amount; }
});

const onProofChange = (event) => { props.form.proof = event.target.files[0] ?? null; };
const methodOptions = computed(() => props.paymentMethods.map((m) => ({ value: m.value, label: m.label })));
</script>

<template>
    <form class="space-y-8" @submit.prevent="emit('submit')">
        <!-- Type de versement -->
        <div>
            <h3 class="mb-3 font-heading text-lg font-semibold text-hsi-navy">Type de versement</h3>
            <div class="grid gap-3 md:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition" :class="form.kind === 'quarterly' ? 'border-hsi-gold bg-hsi-gold/5 ring-2 ring-hsi-gold/20' : 'border-hsi-border hover:border-hsi-gold/50'">
                    <input v-model="form.kind" type="radio" value="quarterly" class="mt-1 h-4 w-4">
                    <span>
                        <span class="flex items-center gap-2 font-semibold text-hsi-navy"><CalendarClock class="h-4 w-4 text-hsi-gold" aria-hidden="true" /> Contribution trimestrielle</span>
                        <span class="mt-1 block text-sm text-hsi-slate">{{ money(rules.quarterly_amount) }} tous les 3 mois, à verser avant le {{ rules.due_day }} du premier mois du trimestre.</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition" :class="form.kind === 'voluntary' ? 'border-hsi-gold bg-hsi-gold/5 ring-2 ring-hsi-gold/20' : 'border-hsi-border hover:border-hsi-gold/50'">
                    <input v-model="form.kind" type="radio" value="voluntary" class="mt-1 h-4 w-4">
                    <span>
                        <span class="flex items-center gap-2 font-semibold text-hsi-navy"><HandCoins class="h-4 w-4 text-hsi-gold" aria-hidden="true" /> Contribution volontaire</span>
                        <span class="mt-1 block text-sm text-hsi-slate">Un montant en plus de la contribution obligatoire. Aucun membre ne doit y être poussé (section 4.1).</span>
                    </span>
                </label>
            </div>
        </div>

        <div>
            <h3 class="mb-4 font-heading text-lg font-semibold text-hsi-navy">Informations du versement</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div v-if="canSelectMember" class="md:col-span-2">
                    <label :class="labelClass" for="member">Membre *</label>
                    <HsiSearchSelect id="member" v-model="form.user_id" :options="memberOptions" placeholder="Sélectionner un membre" search-placeholder="Nom, zone ou email…" required />
                    <p v-if="form.errors.user_id" class="mt-1 text-xs text-hsi-danger">{{ form.errors.user_id }}</p>
                </div>

                <template v-if="form.kind === 'quarterly'">
                    <div>
                        <label :class="labelClass">Trimestre payé *</label>
                        <div class="grid grid-cols-4 gap-2">
                            <button v-for="q in quarters" :key="q.value" type="button" class="rounded-xl border px-2 py-2 text-center transition" :class="form.quarter === q.value ? 'border-hsi-navy bg-hsi-navy text-white' : 'border-hsi-border hover:border-hsi-gold'" :title="q.months" @click="form.quarter = q.value">
                                <span class="block text-sm font-semibold">{{ q.label }}</span>
                                <span class="block truncate text-[10px] opacity-80">{{ q.months }}</span>
                            </button>
                        </div>
                        <p v-if="form.errors.quarter" class="mt-1 text-xs text-hsi-danger">{{ form.errors.quarter }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="year">Année *</label>
                        <select id="year" v-model="form.payment_year" :class="inputClass" required>
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                </template>

                <div>
                    <label :class="labelClass" for="paid-at">{{ canSelectMember ? 'Date de réception du paiement *' : 'Date de votre paiement *' }}</label>
                    <input id="paid-at" v-model="form.paid_at" type="date" :max="new Date().toISOString().slice(0, 10)" :class="inputClass" :required="form.kind === 'quarterly'">
                    <p v-if="form.errors.paid_at" class="mt-1 text-xs text-hsi-danger">{{ form.errors.paid_at }}</p>
                </div>
                <div>
                    <label :class="labelClass" for="amount">Montant versé ({{ $page.props.currency.symbol }}) *</label>
                    <input id="amount" v-model="form.amount" type="number" step="0.01" min="1" :class="inputClass" required>
                    <p v-if="form.errors.amount" class="mt-1 text-xs text-hsi-danger">{{ form.errors.amount }}</p>
                    <p v-else-if="amountTooLow" class="mt-1 text-xs text-hsi-warning">Montant inférieur à la contribution trimestrielle ({{ money(rules.quarterly_amount) }}).</p>
                </div>

                <!-- Récapitulatif du règlement -->
                <div v-if="form.kind === 'quarterly' && dueDate" class="md:col-span-2 rounded-2xl border border-hsi-border bg-hsi-muted/40 p-4">
                    <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-hsi-navy"><Info class="h-4 w-4 text-hsi-gold" aria-hidden="true" /> T{{ form.quarter }} {{ form.payment_year }} — date limite : {{ fmtDate(dueDate) }}</p>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-1.5 text-sm sm:grid-cols-4">
                        <div><dt class="text-xs text-hsi-slate-light">Contribution</dt><dd class="font-semibold text-hsi-navy">{{ money(rules.quarterly_amount) }}</dd></div>
                        <div><dt class="text-xs text-hsi-slate-light">Pénalité de retard</dt><dd class="font-semibold" :class="penaltyApplied > 0 ? 'text-hsi-danger' : 'text-hsi-navy'">{{ money(penaltyApplied) }}<span v-if="openDue?.penalty_waived" class="ml-1 text-xs font-normal text-hsi-slate-light">(annulée)</span></dd></div>
                        <div><dt class="text-xs text-hsi-slate-light">Pénalités reportées</dt><dd class="font-semibold text-hsi-navy">{{ money(carried) }}</dd></div>
                        <div><dt class="text-xs text-hsi-slate-light">Total attendu</dt><dd class="font-heading text-lg font-semibold text-hsi-navy">{{ money(expected) }}</dd></div>
                    </dl>
                    <p v-if="penalty > 0" class="mt-3 text-xs text-hsi-slate">Paiement reçu après le {{ rules.due_day }} : {{ money(rules.penalty_amount) }} par période de {{ rules.penalty_period_days }} jours entamée à partir du {{ rules.penalty_start_day }}. En cas d’erreur bancaire justifiée, le trésorier peut l’annuler.</p>
                </div>

                <div>
                    <label :class="labelClass" for="method">Méthode de paiement *</label>
                    <HsiSearchSelect id="method" v-model="form.payment_method" :options="methodOptions" placeholder="Sélectionner" required />
                    <p v-if="form.errors.payment_method" class="mt-1 text-xs text-hsi-danger">{{ form.errors.payment_method }}</p>
                </div>
                <div>
                    <label :class="labelClass" for="reference">Référence du virement</label>
                    <input id="reference" v-model="form.reference" type="text" :class="inputClass" maxlength="150">
                </div>
                <div class="md:col-span-2">
                    <label :class="labelClass" for="notes">Notes</label>
                    <textarea id="notes" v-model="form.notes" rows="3" :class="inputClass" />
                </div>
                <div class="md:col-span-2">
                    <label :class="labelClass">Preuve de paiement (jpg, png, pdf — max 5 Mo)</label>
                    <input type="file" accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-sm text-hsi-slate file:mr-4 file:rounded-lg file:border-0 file:bg-hsi-muted file:px-4 file:py-2 file:text-sm file:font-medium file:text-hsi-navy hover:file:bg-hsi-border" @change="onProofChange">
                    <p v-if="form.errors.proof" class="mt-1 text-xs text-hsi-danger">{{ form.errors.proof }}</p>
                </div>
            </div>
        </div>

        <p v-if="zoneInfo" class="flex items-start gap-2 rounded-2xl bg-blue-50/70 px-4 py-3 text-sm text-hsi-navy">
            <ShieldCheck class="mt-0.5 h-4 w-4 shrink-0 text-[#1D4ED8]" aria-hidden="true" />
            <span>
                <template v-if="zoneInfo.zone">Zone {{ zoneInfo.zone }} · </template>
                <template v-if="zoneInfo.treasurers.length">Trésorier(s) : <strong>{{ zoneInfo.treasurers.join(', ') }}</strong>. </template>
                La réception sera confirmée sous {{ rules.confirmation_days }} jours ouvrés.
            </span>
        </p>

        <div class="flex items-center justify-end gap-3 border-t border-hsi-border pt-4">
            <slot name="cancel" />
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-hsi-navy px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-hsi-navy-soft disabled:opacity-50" :disabled="form.processing">
                {{ isEdit ? 'Enregistrer' : 'Déclarer la cotisation' }}
            </button>
        </div>
    </form>
</template>
