<script setup>
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2 } from 'lucide-vue-next';
import HsiSidebar from '@/Components/Hsi/HsiSidebar.vue';
import HsiTopbar from '@/Components/Hsi/HsiTopbar.vue';
import { useI18n } from '@/i18n';

const page = usePage();
const { locale } = useI18n();
const sidebarOpen = ref(false);

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};
</script>

<template>
    <div class="flex min-h-screen bg-[#F6F4EE]" :lang="locale">
        <HsiSidebar
            :open="sidebarOpen"
            @close="closeSidebar"
        />

        <div class="flex min-w-0 flex-1 flex-col">
            <HsiTopbar @toggle-sidebar="toggleSidebar" />

            <main class="flex-1 p-4 md:p-6 lg:p-8">
                <div
                    v-if="page.props.flash?.success"
                    class="mb-6 flex items-center gap-3 rounded-2xl border border-hsi-success/25 bg-hsi-success/[0.07] px-4 py-3 text-sm font-medium text-[#166534]"
                    role="status"
                >
                    <CheckCircle2 class="h-5 w-5 shrink-0" aria-hidden="true" />
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="mb-6 flex items-center gap-3 rounded-2xl border border-hsi-danger/25 bg-red-50 px-4 py-3 text-sm font-medium text-hsi-danger"
                    role="alert"
                >
                    <AlertTriangle class="h-5 w-5 shrink-0" aria-hidden="true" />
                    {{ page.props.flash.error }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
