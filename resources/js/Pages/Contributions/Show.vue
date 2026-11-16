<script setup>
import { tr } from '@/i18n';
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Check, Ban, Trash2, Download, FileText } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiBadge from '@/Components/Hsi/HsiBadge.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';
import HsiAvatar from '@/Components/Hsi/HsiAvatar.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';

defineProps({
    payment: Object,
    activities: Array,
    can: Object,
});

const showRejectModal = ref(false);
const rejectionReason = ref('');

const validatePayment = (id) => {
    if (confirm(tr('Valider cette cotisation ?'))) {
        router.patch(route('contributions.validate', id));
    }
};

const submitReject = (id) => {
    if (rejectionReason.value.length < 5) return;
    router.patch(route('contributions.reject', id), {
        rejection_reason: rejectionReason.value,
    }, {
        onSuccess: () => {
            showRejectModal.value = false;
            rejectionReason.value = '';
        },
    });
};

const destroyPayment = (id) => {
    if (confirm(tr('Supprimer définitivement cette cotisation ?'))) {
        router.delete(route('contributions.destroy', id));
    }
};

const formatFileSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1048576) return tr('{n} Ko', { n: Math.round(bytes / 1024) });
    return tr('{n} Mo', { n: (bytes / 1048576).toFixed(1) });
};
</script>

<template>
    <Head :title="`${$tr('Cotisation')} — ${payment.member.name}`" />

    <AuthenticatedLayout>
        <HsiPageHeader
            :title="'Cotisation '"
            :accent="payment.member.name"
            :subtitle="`${$tr(payment.period)} — ${payment.amount_formatted}`"
        >
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="can.edit"
                        :href="route('contributions.edit', payment.id)"
                    >
                        <HsiButton variant="outline">
                            <Pencil class="h-4 w-4" />
                            Modifier
                        </HsiButton>
                    </Link>
                    <HsiButton
                        v-if="can.validate"
                        @click="validatePayment(payment.id)"
                    >
                        <Check class="h-4 w-4" />
                        Valider
                    </HsiButton>
                    <HsiButton
                        v-if="can.reject"
                        variant="danger"
                        @click="showRejectModal = true"
                    >
                        <Ban class="h-4 w-4" />
                        Refuser
                    </HsiButton>
                    <HsiButton
                        v-if="can.delete"
                        variant="danger"
                        @click="destroyPayment(payment.id)"
                    >
                        <Trash2 class="h-4 w-4" />
                        Supprimer
                    </HsiButton>
                </div>
            </template>
        </HsiPageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <HsiCard padding="lg">
                    <div class="flex items-start gap-4 mb-6">
                        <HsiAvatar
                            :name="payment.member.name"
                            size="lg"
                        />
                        <div>
                            <h2 class="font-heading text-xl font-semibold text-hsi-navy">
                                {{ payment.member.name }}
                            </h2>
                            <p class="text-sm text-hsi-slate-light">{{ payment.member.email }}</p>
                            <HsiBadge
                                :variant="payment.status_variant"
                                class="mt-2"
                            >
                                {{ payment.status_label }}
                            </HsiBadge>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-hsi-slate-light uppercase tracking-wide">Montant</p>
                            <p class="text-lg font-semibold text-hsi-navy">{{ payment.amount_formatted }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-hsi-slate-light uppercase tracking-wide">Période</p>
                            <p class="text-sm font-medium">{{ payment.period }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-hsi-slate-light uppercase tracking-wide">Méthode</p>
                            <p class="text-sm font-medium">{{ payment.payment_method_label }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-hsi-slate-light uppercase tracking-wide">Référence</p>
                            <p class="text-sm font-medium">{{ payment.reference }}</p>
                        </div>
                        <div v-if="payment.plan">
                            <p class="text-xs text-hsi-slate-light uppercase tracking-wide">Plan</p>
                            <p class="text-sm font-medium">{{ payment.plan.name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-hsi-slate-light uppercase tracking-wide">Date de paiement</p>
                            <p class="text-sm font-medium">{{ payment.paid_at ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-hsi-slate-light uppercase tracking-wide">Soumis le</p>
                            <p class="text-sm font-medium">{{ payment.submitted_at ?? '—' }}</p>
                        </div>
                        <div v-if="payment.validated_at">
                            <p class="text-xs text-hsi-slate-light uppercase tracking-wide">Validé le</p>
                            <p class="text-sm font-medium">{{ payment.validated_at }} par {{ payment.validated_by }}</p>
                        </div>
                        <div v-if="payment.rejected_at">
                            <p class="text-xs text-hsi-slate-light uppercase tracking-wide">Refusé le</p>
                            <p class="text-sm font-medium">{{ payment.rejected_at }} par {{ payment.rejecter }}</p>
                        </div>
                    </div>

                    <div
                        v-if="payment.rejection_reason"
                        class="mt-4 p-4 rounded-xl bg-red-50 border border-red-200"
                    >
                        <p class="text-xs font-medium text-hsi-danger uppercase mb-1">Motif du refus</p>
                        <p class="text-sm text-hsi-navy">{{ payment.rejection_reason }}</p>
                    </div>

                    <div
                        v-if="payment.notes"
                        class="mt-4"
                    >
                        <p class="text-xs text-hsi-slate-light uppercase tracking-wide mb-1">Notes</p>
                        <p class="text-sm text-hsi-navy">{{ payment.notes }}</p>
                    </div>
                </HsiCard>

                <HsiCard
                    v-if="payment.proofs?.length"
                    padding="lg"
                >
                    <HsiSectionTitle title="Preuves de paiement" />
                    <div class="space-y-3 mt-4">
                        <div
                            v-for="proof in payment.proofs"
                            :key="proof.id"
                            class="flex items-center justify-between p-3 rounded-xl border border-hsi-border bg-hsi-muted/30"
                        >
                            <div class="flex items-center gap-3">
                                <FileText class="h-5 w-5 text-hsi-gold" />
                                <div>
                                    <p class="text-sm font-medium">{{ proof.original_name }}</p>
                                    <p class="text-xs text-hsi-slate-light">
                                        {{ formatFileSize(proof.file_size) }} · {{ proof.uploaded_at }}
                                    </p>
                                </div>
                            </div>
                            <a
                                :href="proof.download_url"
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-hsi-gold hover:text-hsi-gold-light"
                            >
                                <Download class="h-4 w-4" />
                                Télécharger
                            </a>
                        </div>
                    </div>
                </HsiCard>
            </div>

            <div>
                <HsiCard padding="lg">
                    <HsiSectionTitle title="Historique d'activité" />
                    <div
                        v-if="!activities.length"
                        class="mt-4 text-sm text-hsi-slate-light"
                    >
                        Aucune activité enregistrée.
                    </div>
                    <ul
                        v-else
                        class="mt-4 space-y-3"
                    >
                        <li
                            v-for="activity in activities"
                            :key="activity.id"
                            class="text-sm border-l-2 border-hsi-gold/40 pl-3"
                        >
                            <p class="font-medium text-hsi-navy">{{ activity.description }}</p>
                            <p class="text-xs text-hsi-slate-light">
                                {{ activity.created_at }}
                                <span v-if="activity.causer"> · {{ activity.causer }}</span>
                            </p>
                        </li>
                    </ul>
                </HsiCard>
            </div>
        </div>

        <!-- Modal refus -->
        <div
            v-if="showRejectModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-hsi-navy/50 backdrop-blur-sm p-4"
        >
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                <h3 class="font-heading text-lg font-semibold text-hsi-navy mb-4">
                    Refuser la cotisation
                </h3>
                <textarea
                    v-model="rejectionReason"
                    rows="4"
                    class="w-full rounded-xl border border-hsi-border px-4 py-2.5 text-sm"
                    placeholder="Motif du refus (min. 5 caractères)..."
                />
                <div class="flex justify-end gap-2 mt-4">
                    <HsiButton
                        variant="ghost"
                        @click="showRejectModal = false"
                    >
                        Annuler
                    </HsiButton>
                    <HsiButton
                        variant="danger"
                        :disabled="rejectionReason.length < 5"
                        @click="submitReject(payment.id)"
                    >
                        Confirmer le refus
                    </HsiButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
