<script setup>
defineProps({
    columns: {
        type: Array,
        default: () => [],
    },
    rows: {
        type: Array,
        default: () => [],
    },
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="overflow-x-auto -mx-2 px-2">
        <!-- Tableaux compacts (cartes du tableau de bord) : pas de largeur minimale, le texte passe à la ligne. -->
        <table class="w-full" :class="compact ? '' : 'min-w-[480px]'">
            <thead>
                <tr class="border-b border-hsi-border">
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        :class="[
                            'text-left text-[11px] font-semibold uppercase tracking-wider text-hsi-slate-light',
                            compact ? 'px-3 py-2' : 'px-4 py-3',
                            col.align === 'right' ? 'text-right' : '',
                            col.align === 'center' ? 'text-center' : '',
                        ]"
                    >
                        {{ col.label }}
                    </th>
                    <th
                        v-if="$slots.actions"
                        class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-hsi-slate-light"
                    />
                </tr>
            </thead>
            <tbody>
                <slot name="body">
                    <tr
                        v-for="(row, index) in rows"
                        :key="index"
                        class="border-b border-hsi-border/60 last:border-0 hover:bg-hsi-muted/40 transition-colors"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            :class="[
                                'text-sm text-hsi-navy',
                                compact ? 'px-3 py-2.5' : 'px-4 py-3.5',
                                col.align === 'right' ? 'text-right' : '',
                                col.align === 'center' ? 'text-center' : '',
                            ]"
                        >
                            <slot
                                :name="`cell-${col.key}`"
                                :row="row"
                                :value="row[col.key]"
                            >
                                {{ row[col.key] }}
                            </slot>
                        </td>
                        <td
                            v-if="$slots.actions"
                            :class="[
                                'text-right',
                                compact ? 'px-3 py-2.5' : 'px-4 py-3.5',
                            ]"
                        >
                            <slot
                                name="actions"
                                :row="row"
                            />
                        </td>
                    </tr>
                </slot>
            </tbody>
        </table>
    </div>
</template>
