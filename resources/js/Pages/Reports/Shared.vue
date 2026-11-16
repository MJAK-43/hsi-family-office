<script setup>
import { formatMoney } from '@/currency';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FileText } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';

defineProps({ shared: Object, report: Object });
const money = (v) => formatMoney(v, { decimals: 2 });
</script>

<template>
    <Head :title="shared.title" />
    <AuthenticatedLayout>
        <HsiPageHeader :title="shared.title" :subtitle="`${shared.period} · ${$tr('partagé le')} ${shared.shared_at}`">
            <template #actions>
                <Link :href="route('reports.index')"><HsiButton variant="ghost"><ArrowLeft class="h-4 w-4" aria-hidden="true" /> Rapports</HsiButton></Link>
                <a :href="route('reports.shared.pdf', shared.id)"><HsiButton variant="outline"><FileText class="h-4 w-4" aria-hidden="true" /> PDF</HsiButton></a>
            </template>
        </HsiPageHeader>

        <p v-if="shared.comment" class="mb-6 whitespace-pre-wrap rounded-2xl border-l-4 border-hsi-gold bg-white p-4 text-sm text-hsi-navy" translate="no">{{ shared.comment }}</p>

        <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-hsi-border bg-white p-4"><p class="text-xs text-hsi-slate-light">Total des contributions reçues</p><p class="mt-1 text-xl font-semibold text-hsi-navy">{{ money(report.kpis.contributions) }}</p></div>
            <div class="rounded-2xl border border-hsi-border bg-white p-4"><p class="text-xs text-hsi-slate-light">Retards et pénalités</p><p class="mt-1 text-xl font-semibold text-hsi-navy">{{ money(report.kpis.contribution_penalties + report.kpis.loan_penalties) }}</p><p class="text-[11px] text-hsi-slate-light">{{ report.kpis.late_contributions }} cotisation(s), {{ report.kpis.late_installments }} échéance(s) en retard</p></div>
            <div class="rounded-2xl border border-hsi-border bg-white p-4"><p class="text-xs text-hsi-slate-light">Solde total du compte</p><p class="mt-1 text-xl font-semibold text-hsi-navy">{{ money(report.kpis.balance) }}</p></div>
            <div class="rounded-2xl border border-hsi-border bg-white p-4"><p class="text-xs text-hsi-slate-light">Remboursements en cours (reste dû)</p><p class="mt-1 text-xl font-semibold text-hsi-navy">{{ money(report.kpis.outstanding) }}</p></div>
        </div>

        <HsiCard padding="lg" class="mb-6">
            <HsiSectionTitle title="État des remboursements" />
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead><tr class="border-b border-hsi-border text-left text-hsi-slate-light"><th class="py-2">Projet</th><th>Emprunteur</th><th class="text-right">Remboursé</th><th class="text-right">Reste dû</th><th>Prochaine échéance</th><th>État</th></tr></thead>
                    <tbody>
                        <tr v-for="l in report.loans" :key="l.id" class="border-b border-hsi-border/50"><td class="py-2">{{ l.title }}</td><td>{{ l.borrower }}</td><td class="text-right">{{ money(l.paid) }}</td><td class="text-right">{{ money(l.remaining) }}</td><td>{{ l.next_due || '—' }}</td><td>{{ l.status }}</td></tr>
                        <tr v-if="!report.loans.length"><td colspan="6" class="py-4 text-center text-hsi-slate-light">Aucun prêt.</td></tr>
                    </tbody>
                </table>
            </div>
        </HsiCard>

        <HsiCard padding="lg">
            <HsiSectionTitle title="Registre des membres" />
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead><tr class="border-b border-hsi-border text-left text-hsi-slate-light"><th class="py-2">Membre</th><th class="text-right">Cotisations</th><th class="text-right">Part du fonds</th><th class="text-right">Pénalités dues</th></tr></thead>
                    <tbody>
                        <tr v-for="m in report.members" :key="m.id" class="border-b border-hsi-border/50"><td class="py-2">{{ m.name }}</td><td class="text-right">{{ money(m.contributions) }}</td><td class="text-right">{{ m.share_percent }} %</td><td class="text-right">{{ money(m.penalties) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </HsiCard>
    </AuthenticatedLayout>
</template>
