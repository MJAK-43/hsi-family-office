<script setup>
import { reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarPlus, DoorOpen, Plus, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';
import { tr } from '@/i18n';

const props = defineProps({ mine: Array, all: Array, preview: Object, accounts: Array, canManage: Boolean });

const requestForm = useForm({ reason: '', confirm: false });
const submitRequest = () => requestForm.post(route('withdrawals.store'), { preserveScroll: true });
const cancel = (w) => { if (confirm(tr('Annuler cette demande de retrait ?'))) router.patch(route('withdrawals.cancel', w.id), {}, { preserveScroll: true }); };
const active = props.mine.find((w) => ['requested', 'scheduled'].includes(w.status));

// Préparation du calendrier : versements répartis, modifiables.
const editing = ref(null);
const schedule = reactive({ installments: [], funds_engaged_in_loans: false, notes: '' });
const addMonths = (iso, n) => { const d = new Date(`${iso}T00:00:00`); d.setMonth(d.getMonth() + n); return d.toISOString().slice(0, 10); };
const startSchedule = (w) => {
    editing.value = w.id;
    schedule.funds_engaged_in_loans = w.funds_engaged_in_loans;
    schedule.notes = w.notes ?? '';
    if (w.installments.length) {
        schedule.installments = w.installments.map((i) => ({ due_date: i.due_date_raw, amount: i.amount_raw }));
    } else {
        const count = 3;
        const base = Math.floor((w.refund_amount_raw / count) * 100) / 100;
        schedule.installments = Array.from({ length: count }, (_, i) => ({
            due_date: addMonths(w.effective_date_raw, i + 1),
            amount: i === count - 1 ? Math.round((w.refund_amount_raw - base * (count - 1)) * 100) / 100 : base,
        }));
    }
};
const scheduleTotal = () => schedule.installments.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0).toFixed(2);
const saveSchedule = (w) => router.put(route('withdrawals.schedule', w.id), schedule, { preserveScroll: true, onSuccess: () => { editing.value = null; } });
const payAccount = ref({});
const pay = (installment) => router.post(route('withdrawals.pay', installment.id), { fund_account_id: payAccount.value[installment.id] }, { preserveScroll: true });
const variant = { requested: 'warning', scheduled: 'info', completed: 'success', cancelled: 'neutral' };
const inputClass = 'rounded-xl border border-hsi-border bg-white px-3 py-2 text-sm';
</script>

<template>
    <Head title="Retrait du fonds" />
    <AuthenticatedLayout>
        <HsiPageHeader title="Retrait " accent="du fonds" subtitle="Section 8 du règlement : préavis, remboursement de vos contributions et calendrier" />

        <p v-if="$page.props.errors?.installments || $page.props.errors?.payment" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $page.props.errors.installments || $page.props.errors.payment }}</p>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <HsiCard padding="lg">
                <HsiSectionTitle title="Ma situation" />
                <ul class="space-y-2 text-sm text-hsi-slate">
                    <li>Préavis : <strong class="text-hsi-navy">{{ preview.notice_months }} mois</strong> (départ au plus tôt le {{ preview.effective_date }}).</li>
                    <li>Total de vos contributions : <strong class="text-hsi-navy">{{ preview.total }}</strong>.</li>
                    <li>Remboursement : <strong class="text-hsi-navy">{{ preview.percent }} %</strong>, soit environ <strong class="text-hsi-navy">{{ preview.refund }}</strong>. Le reste est conservé par le fonds.</li>
                    <li>Délai : 1 à 6 mois après le départ, jusqu’à {{ preview.max_months }} mois si l’argent est engagé dans un prêt en cours.</li>
                    <li>Le calendrier vous est communiqué sous deux semaines.</li>
                </ul>

                <div v-for="w in mine" :key="w.id" class="mt-5 rounded-2xl border border-hsi-border p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-medium text-hsi-navy">Demande du {{ w.requested_on }} · départ le {{ w.effective_date }}</p>
                        <HsiBadge :variant="variant[w.status]">{{ w.status_label }}</HsiBadge>
                    </div>
                    <p class="mt-1 text-sm text-hsi-slate">À rembourser : <strong>{{ w.refund_amount }}</strong> ({{ w.refund_percent }} % de {{ w.total_contributions }})</p>
                    <ul v-if="w.installments.length" class="mt-2 space-y-1 text-sm">
                        <li v-for="i in w.installments" :key="i.id" class="flex justify-between rounded-lg bg-hsi-muted/50 px-3 py-1.5"><span>{{ i.due_date }}</span><span>{{ i.amount }} <template v-if="i.paid_at">· versé le {{ i.paid_at }}</template></span></li>
                    </ul>
                    <HsiButton v-if="['requested', 'scheduled'].includes(w.status) && !w.installments.some((i) => i.paid_at)" size="sm" variant="ghost" class="mt-2" @click="cancel(w)">Annuler ma demande</HsiButton>
                </div>

                <form v-if="!active" class="mt-5 space-y-3 border-t border-hsi-border pt-4" @submit.prevent="submitRequest">
                    <h3 class="flex items-center gap-2 font-semibold text-hsi-navy"><DoorOpen class="h-4 w-4" aria-hidden="true" /> Demander à quitter le fonds</h3>
                    <textarea v-model="requestForm.reason" rows="3" required minlength="10" class="w-full rounded-xl border border-hsi-border px-3 py-2 text-sm" placeholder="Raison de votre départ" />
                    <label class="flex items-start gap-2 text-sm text-hsi-navy"><input v-model="requestForm.confirm" type="checkbox" class="mt-0.5 h-4 w-4 rounded"> J’ai compris le préavis et les conditions de remboursement.</label>
                    <p v-if="requestForm.errors.reason || requestForm.errors.confirm" class="text-sm text-hsi-danger">{{ requestForm.errors.reason || requestForm.errors.confirm }}</p>
                    <HsiButton type="submit" variant="danger" :disabled="requestForm.processing || !requestForm.confirm">Envoyer ma demande</HsiButton>
                </form>
            </HsiCard>

            <HsiCard v-if="canManage" padding="lg">
                <HsiSectionTitle title="Demandes de retrait des membres" />
                <div class="space-y-4">
                    <article v-for="w in all" :key="w.id" class="rounded-2xl border border-hsi-border p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-hsi-navy">{{ w.member }}</p>
                            <HsiBadge :variant="variant[w.status]">{{ w.status_label }}</HsiBadge>
                        </div>
                        <p class="mt-1 text-sm text-hsi-slate">Demande du {{ w.requested_on }} · départ le {{ w.effective_date }} · {{ w.refund_amount }} à rembourser</p>
                        <p class="mt-1 text-sm italic text-hsi-slate" translate="no">« {{ w.reason }} »</p>
                        <p v-if="w.late_schedule" class="mt-2 rounded-lg bg-amber-50 px-3 py-1.5 text-sm text-amber-900">Calendrier à communiquer avant le {{ w.schedule_deadline }} : délai dépassé.</p>

                        <div v-if="editing === w.id" class="mt-3 space-y-2">
                            <div v-for="(row, index) in schedule.installments" :key="index" class="flex flex-wrap items-center gap-2">
                                <input v-model="row.due_date" type="date" :class="inputClass" required>
                                <input v-model="row.amount" type="number" step="0.01" min="0.01" :class="inputClass" required>
                                <button type="button" class="rounded p-1 text-hsi-danger" :aria-label="$tr('Supprimer')" @click="schedule.installments.splice(index, 1)"><Trash2 class="h-4 w-4" /></button>
                            </div>
                            <p class="text-sm" :class="scheduleTotal() === w.refund_amount_raw.toFixed(2) ? 'text-hsi-success' : 'text-hsi-danger'">Total : {{ scheduleTotal() }} $ / {{ w.refund_amount_raw.toFixed(2) }} $</p>
                            <label class="flex items-center gap-2 text-sm"><input v-model="schedule.funds_engaged_in_loans" type="checkbox" class="h-4 w-4 rounded"> Argent engagé dans un prêt en cours (jusqu’à 12 mois)</label>
                            <input v-model="schedule.notes" maxlength="2000" :class="[inputClass, 'w-full']" placeholder="Note pour le membre (facultatif)">
                            <div class="flex flex-wrap gap-2">
                                <HsiButton size="sm" variant="outline" @click="schedule.installments.push({ due_date: addMonths(w.effective_date_raw, schedule.installments.length + 1), amount: 0 })"><Plus class="h-3.5 w-3.5" aria-hidden="true" /> Versement</HsiButton>
                                <HsiButton size="sm" @click="saveSchedule(w)">Communiquer le calendrier</HsiButton>
                                <HsiButton size="sm" variant="ghost" @click="editing = null">Annuler</HsiButton>
                            </div>
                        </div>
                        <template v-else>
                            <ul v-if="w.installments.length" class="mt-3 space-y-2 text-sm">
                                <li v-for="i in w.installments" :key="i.id" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-hsi-muted/50 px-3 py-2">
                                    <span>{{ i.due_date }} · <strong>{{ i.amount }}</strong></span>
                                    <span v-if="i.paid_at" class="text-hsi-success">Versé le {{ i.paid_at }}</span>
                                    <span v-else-if="i.pending_approval" class="text-amber-700">En attente de validation</span>
                                    <span v-else-if="w.status === 'scheduled'" class="flex items-center gap-2">
                                        <select v-model="payAccount[i.id]" class="rounded-lg border border-hsi-border px-2 py-1 text-xs"><option :value="undefined" disabled>Compte</option><option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }} ({{ a.current_balance }} $)</option></select>
                                        <HsiButton size="sm" :disabled="!payAccount[i.id]" @click="pay(i)">Verser</HsiButton>
                                    </span>
                                </li>
                            </ul>
                            <HsiButton v-if="['requested', 'scheduled'].includes(w.status) && !w.installments.some((i) => i.paid_at)" size="sm" variant="outline" class="mt-3" @click="startSchedule(w)"><CalendarPlus class="h-4 w-4" aria-hidden="true" /> {{ w.installments.length ? 'Modifier le calendrier' : 'Préparer le calendrier' }}</HsiButton>
                        </template>
                    </article>
                    <p v-if="!all.length" class="text-sm text-hsi-slate-light">Aucune demande de retrait.</p>
                </div>
            </HsiCard>
        </div>
    </AuthenticatedLayout>
</template>
