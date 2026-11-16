<script setup>
import { computed, ref } from 'vue';
import { AlertCircle, Eye, EyeOff } from 'lucide-vue-next';
import { useI18n } from '@/i18n';

const model = defineModel({ type: String, default: '' });

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    autocomplete: { type: String, default: undefined },
    placeholder: { type: String, default: '' },
    required: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
});

const { t } = useI18n();
const visible = ref(false);
const capsLock = ref(false);
const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => (isPassword.value && visible.value ? 'text' : props.type));
const describedBy = computed(() => [props.error && `${props.id}-error`, props.hint && `${props.id}-hint`].filter(Boolean).join(' ') || undefined);

const checkCaps = (event) => {
    if (isPassword.value && typeof event.getModifierState === 'function') {
        capsLock.value = event.getModifierState('CapsLock');
    }
};
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-center justify-between gap-3">
            <label :for="id" class="text-sm font-semibold text-hsi-navy">{{ label }}</label>
            <slot name="label-action" />
        </div>

        <div class="relative">
            <input
                :id="id"
                v-model="model"
                :type="inputType"
                :autocomplete="autocomplete"
                :placeholder="placeholder"
                :required="required"
                :autofocus="autofocus"
                :readonly="readonly"
                :aria-invalid="error ? 'true' : 'false'"
                :aria-describedby="describedBy"
                class="block h-[52px] w-full rounded-2xl border bg-[#F4F6FA] px-4 text-[15px] text-hsi-navy placeholder:text-hsi-slate-light transition duration-150 focus:bg-white focus:outline-none focus:ring-4"
                :class="[
                    isPassword ? 'pr-12' : '',
                    readonly ? 'cursor-default text-hsi-slate' : '',
                    error
                        ? 'border-hsi-danger/60 focus:border-hsi-danger focus:ring-hsi-danger/15'
                        : 'border-transparent hover:border-hsi-border focus:border-hsi-gold focus:ring-hsi-gold/20',
                ]"
                @keydown="checkCaps"
                @keyup="checkCaps"
                @blur="capsLock = false"
            >
            <button
                v-if="isPassword"
                type="button"
                class="absolute inset-y-0 right-2 my-auto flex h-9 w-9 items-center justify-center rounded-xl text-hsi-slate transition hover:bg-hsi-navy/5 hover:text-hsi-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-hsi-gold"
                :aria-label="visible ? t('fields.hidePassword') : t('fields.showPassword')"
                :aria-pressed="visible"
                @click="visible = !visible"
            >
                <EyeOff v-if="visible" class="h-5 w-5" aria-hidden="true" />
                <Eye v-else class="h-5 w-5" aria-hidden="true" />
            </button>
        </div>

        <p v-if="capsLock" class="text-[13px] font-medium text-hsi-warning">{{ t('fields.capsLock') }}</p>
        <p v-if="error" :id="`${id}-error`" class="flex items-start gap-1.5 text-[13px] font-medium text-hsi-danger" role="alert">
            <AlertCircle class="mt-px h-4 w-4 shrink-0" aria-hidden="true" />
            <span>{{ error }}</span>
        </p>
        <p v-else-if="hint" :id="`${id}-hint`" class="text-[13px] text-hsi-slate">{{ hint }}</p>
        <slot name="after" />
    </div>
</template>
