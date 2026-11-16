<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { BookOpenCheck, CheckCircle2, Info, ShieldCheck } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HsiPageHeader from '@/Components/Hsi/HsiPageHeader.vue';
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiButton from '@/Components/Hsi/HsiButton.vue';

defineProps({
    sections: { type: Array, required: true },
    version: { type: String, required: true },
    mustAccept: { type: Boolean, default: false },
    acceptedAt: { type: String, default: null },
});

const form = useForm({ accepted: false });
const accept = () => form.post(route('policies.accept'));

// « … l'État de {État}, USA » : les passages entre accolades sont encore à compléter dans le document officiel.
const parts = (text) => text.split(/(\{[^}]+\})/).filter(Boolean).map((chunk) => (
    chunk.startsWith('{') ? { blank: true, text: chunk.slice(1, -1) } : { blank: false, text: chunk }
));
</script>

<template>
    <Head title="Règlement intérieur" />

    <AuthenticatedLayout>
        <HsiPageHeader title="Règlement " accent="intérieur" subtitle="Fonds familial JOKUNG · Règlement intérieur et guide de fonctionnement" />

        <div v-if="mustAccept" class="mb-6 flex items-start gap-3 rounded-2xl border border-hsi-gold/40 bg-hsi-gold/10 p-4 text-sm text-hsi-navy" role="status">
            <Info class="mt-0.5 h-5 w-5 shrink-0 text-[#A8841F]" aria-hidden="true" />
            <p>Avant d’accéder à votre espace, merci de lire le règlement intérieur du fonds puis de confirmer que vous l’acceptez, en bas de cette page.</p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
            <nav class="hidden lg:block" aria-label="Sommaire">
                <div class="sticky top-24 rounded-2xl border border-hsi-border bg-white p-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-hsi-slate-light">Sommaire</p>
                    <ul class="space-y-1 text-sm" translate="no">
                        <li v-for="section in sections" :key="section.id">
                            <a :href="`#${section.id}`" class="block rounded-lg px-2 py-1.5 text-hsi-slate hover:bg-hsi-muted hover:text-hsi-navy">{{ section.title }}</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="min-w-0 space-y-5">
                <HsiCard v-for="section in sections" :id="section.id" :key="section.id" padding="lg" class="scroll-mt-24">
                    <div translate="no" class="space-y-3 text-[15px] leading-relaxed text-hsi-slate">
                        <h2 class="font-heading text-xl font-semibold text-hsi-navy">{{ section.title }}</h2>
                        <template v-for="(block, index) in section.blocks" :key="index">
                            <h3 v-if="block[0] === 'h'" class="pt-2 font-semibold text-hsi-navy">{{ block[1] }}</h3>
                            <p v-else-if="block[0] === 'p'">
                                <template v-for="(part, i) in parts(block[1])" :key="i"><mark v-if="part.blank" class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-900">{{ part.text }}</mark><template v-else>{{ part.text }}</template></template>
                            </p>
                            <p v-else-if="block[0] === 'note'" class="rounded-xl border-l-4 border-hsi-gold bg-hsi-gold/[0.07] px-4 py-3 font-medium text-hsi-navy">{{ block[1] }}</p>
                            <ul v-else-if="block[0] === 'ul'" class="list-disc space-y-1.5 pl-6 marker:text-hsi-gold">
                                <li v-for="(item, i) in block[1]" :key="i">{{ item }}</li>
                            </ul>
                            <div v-else-if="block[0] === 'table'" class="overflow-x-auto rounded-xl border border-hsi-border">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-hsi-navy text-white">
                                        <tr><th v-for="head in block[1]" :key="head" class="px-4 py-2.5 font-semibold">{{ head }}</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, r) in block[2]" :key="r" class="border-t border-hsi-border">
                                            <td v-for="(cell, c) in row" :key="c" class="px-4 py-2.5 align-top" :class="c === 0 ? 'font-medium text-hsi-navy' : ''">
                                                <template v-for="(part, i) in parts(cell)" :key="i"><mark v-if="part.blank" class="rounded bg-amber-100 px-1.5 py-0.5 text-amber-900">{{ part.text }}</mark><template v-else>{{ part.text }}</template></template>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                </HsiCard>

                <HsiCard padding="lg">
                    <div v-if="mustAccept">
                        <div class="mb-4 flex items-center gap-3">
                            <BookOpenCheck class="h-6 w-6 text-[#A8841F]" aria-hidden="true" />
                            <h2 class="font-heading text-xl font-semibold text-hsi-navy">Acceptation du règlement</h2>
                        </div>
                        <form class="space-y-4" @submit.prevent="accept">
                            <label class="flex cursor-pointer items-start gap-3 text-[15px] text-hsi-navy">
                                <input v-model="form.accepted" type="checkbox" class="mt-1 h-5 w-5 rounded-md border-hsi-border text-hsi-navy focus:ring-hsi-gold/40">
                                <span>J’ai lu le règlement intérieur du Fonds familial JOKUNG et j’en accepte toutes les règles.</span>
                            </label>
                            <p v-if="form.errors.accepted" class="text-sm font-medium text-hsi-danger" role="alert">{{ form.errors.accepted }}</p>
                            <p class="text-xs text-hsi-slate-light">Votre acceptation est enregistrée avec la date, l’heure et la version du règlement ({{ version }}). Elle remplace la signature du document papier.</p>
                            <HsiButton type="submit" :disabled="!form.accepted || form.processing">
                                <ShieldCheck class="h-4 w-4" aria-hidden="true" /> J’accepte le règlement
                            </HsiButton>
                        </form>
                    </div>
                    <div v-else class="flex items-center gap-3 text-sm text-hsi-navy">
                        <CheckCircle2 class="h-5 w-5 text-hsi-success" aria-hidden="true" />
                        <p>Vous avez accepté ce règlement le {{ acceptedAt }} (version {{ version }}).</p>
                    </div>
                </HsiCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
