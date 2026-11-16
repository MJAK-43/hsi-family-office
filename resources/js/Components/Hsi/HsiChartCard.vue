<script setup>
import HsiCard from '@/Components/Hsi/HsiCard.vue';
import HsiSectionTitle from '@/Components/Hsi/HsiSectionTitle.vue';

defineProps({
    title: {
        type: String,
        default: null,
    },
    subtitle: {
        type: String,
        default: null,
    },
    padding: {
        type: String,
        default: 'md',
    },
});
</script>

<template>
    <HsiCard :padding="padding">
        <HsiSectionTitle
            v-if="title"
            :title="title"
            :subtitle="subtitle"
        />
        <slot />
    </HsiCard>
</template>
