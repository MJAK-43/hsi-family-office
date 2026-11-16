<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Check, RotateCcw, X } from 'lucide-vue-next';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import { tr } from '@/i18n';

const props = defineProps({ request: { type: Object, required: true } });

const comment = ref('');
const approve = () => router.patch(route('approvals.approve', props.request.id), { comment: comment.value || null }, { preserveScroll: true });
const reject = () => {
    const reason = prompt(tr(props.request.can_cancel ? 'Motif de l’annulation :' : 'Motif du refus :'));
    if (reason?.length >= 5) router.patch(route('approvals.reject', props.request.id), { reason }, { preserveScroll: true });
};
const retry = () => router.patch(route('approvals.retry', props.request.id), {}, { preserveScroll: true });
const variant = { pending: 'warning', executed: 'success', rejected: 'danger', cancelled: 'neutral', failed: 'danger' };
</script>

<template>
    <article class="rounded-2xl border border-hsi-border bg-white p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-semibold text-hsi-navy">
                    <Link v-if="request.subject_url" :href="request.subject_url" class="hover:underline">{{ request.title }}</Link>
                    <template v-else>{{ request.title }}</template>
                </p>
                <p class="text-xs text-hsi-slate-light">{{ request.type }} · demandée par {{ request.requested_by }} le {{ request.created_at }}<template v-if="request.beneficiary"> · bénéficiaire {{ request.beneficiary }}</template></p>
            </div>
            <div class="text-right">
                <p class="text-lg font-semibold text-hsi-navy">{{ request.amount }}</p>
                <HsiBadge :variant="variant[request.status] ?? 'neutral'">{{ request.status_label }}</HsiBadge>
            </div>
        </div>

        <div class="mt-3">
            <div class="flex items-center gap-2 text-sm text-hsi-slate">
                <span>Validations : <strong class="text-hsi-navy">{{ request.approvals }} / {{ request.required }}</strong></span>
                <span class="h-1.5 w-24 rounded-full bg-hsi-muted"><span class="block h-1.5 rounded-full bg-hsi-gold" :style="{ width: `${Math.min(100, (request.approvals / request.required) * 100)}%` }" /></span>
            </div>
            <ul v-if="request.decisions.length" class="mt-2 space-y-0.5 text-xs text-hsi-slate">
                <li v-for="(d, i) in request.decisions" :key="i">{{ d.decision === 'approve' ? '✓' : '✗' }} {{ d.user }} — {{ d.date }}<template v-if="d.comment"> · « {{ d.comment }} »</template></li>
            </ul>
            <p v-if="request.failure_reason" class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ request.failure_reason }}</p>
            <p v-if="request.rejected_reason" class="mt-2 text-sm text-hsi-danger">{{ request.rejected_reason }}</p>
        </div>

        <div v-if="request.can_approve || request.can_cancel || request.can_retry" class="mt-3 flex flex-wrap items-center gap-2 border-t border-hsi-border pt-3">
            <input v-if="request.can_approve" v-model="comment" type="text" maxlength="2000" class="min-w-[180px] flex-1 rounded-xl border border-hsi-border px-3 py-1.5 text-sm" placeholder="Commentaire (facultatif)">
            <HsiButton v-if="request.can_approve" size="sm" @click="approve"><Check class="h-4 w-4" aria-hidden="true" /> Valider</HsiButton>
            <HsiButton v-if="request.can_approve || request.can_cancel" size="sm" variant="ghost" @click="reject"><X class="h-4 w-4" aria-hidden="true" /> {{ request.can_cancel ? 'Annuler ma demande' : 'Refuser' }}</HsiButton>
            <HsiButton v-if="request.can_retry" size="sm" variant="outline" @click="retry"><RotateCcw class="h-4 w-4" aria-hidden="true" /> Relancer l’exécution</HsiButton>
        </div>
    </article>
</template>
