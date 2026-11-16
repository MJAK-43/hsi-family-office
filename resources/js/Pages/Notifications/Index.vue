<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Bell, CheckCheck } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';

defineProps({ notifications: Array });
const readAll = () => router.post(route('notifications.read-all'), {}, { preserveScroll: true });
</script>

<template>
    <Head title="Notifications" />
    <AuthenticatedLayout>
        <HsiPageHeader title="Mes " accent="notifications" subtitle="Décisions, votes, validations, rappels et rapports du fonds">
            <template #actions>
                <HsiButton v-if="notifications.some((n) => !n.read)" variant="outline" @click="readAll"><CheckCheck class="h-4 w-4" aria-hidden="true" /> Tout marquer comme lu</HsiButton>
            </template>
        </HsiPageHeader>

        <ul class="space-y-2">
            <li v-for="item in notifications" :key="item.id">
                <a :href="route('notifications.open', item.id)" class="flex gap-3 rounded-2xl border p-4 transition hover:border-hsi-gold/60" :class="item.read ? 'border-hsi-border bg-white' : 'border-hsi-gold/40 bg-hsi-gold/[0.06]'">
                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :class="item.read ? 'bg-transparent' : 'bg-hsi-gold'" aria-hidden="true" />
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-hsi-navy" translate="no">{{ item.title }}</span>
                        <span class="mt-0.5 block text-sm text-hsi-slate" translate="no">{{ item.message }}</span>
                        <span class="mt-1 block text-xs text-hsi-slate-light">{{ item.date }}</span>
                    </span>
                </a>
            </li>
            <li v-if="!notifications.length" class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-hsi-border p-10 text-sm text-hsi-slate-light">
                <Bell class="h-6 w-6" aria-hidden="true" /> Aucune notification.
            </li>
        </ul>
    </AuthenticatedLayout>
</template>
