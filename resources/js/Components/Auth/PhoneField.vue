<script setup>
import { computed, ref, watch } from 'vue';
import { AlertCircle, CheckCircle2, ChevronDown } from 'lucide-vue-next';
import {
    AsYouType,
    getCountries,
    getCountryCallingCode,
    getExampleNumber,
    parsePhoneNumberFromString,
} from 'libphonenumber-js/max';
import examples from 'libphonenumber-js/mobile/examples';
import { useI18n } from '@/i18n';

/**
 * Champ téléphone international : indicatif du pays + numéro national.
 * La valeur émise est au format international E.164 (ex. +237670000000).
 */
const model = defineModel({ type: String, default: '' });

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, default: 'Téléphone' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    // Pays proposé par défaut (code ISO à 2 lettres), par exemple celui de résidence.
    suggestedCountry: { type: String, default: '' },
});

const { t, locale } = useI18n();
const regionNames = new Intl.DisplayNames([locale.value], { type: 'region' });
const flag = (iso) => iso.toUpperCase().replace(/./g, (c) => String.fromCodePoint(127397 + c.charCodeAt(0)));

const countries = getCountries()
    .map((iso) => ({ iso, name: regionNames.of(iso) ?? iso, dial: getCountryCallingCode(iso), flag: flag(iso) }))
    .sort((a, b) => a.name.localeCompare(b.name, locale.value));

const guessCountry = () => {
    const lang = (navigator.language || '').split('-')[1];
    return countries.some((c) => c.iso === lang) ? lang : 'FR';
};

// Un ancien numéro saisi sans indicatif (« 06 12… ») est interprété avec le pays proposé.
const initial = model.value
    ? (parsePhoneNumberFromString(model.value) ?? parsePhoneNumberFromString(model.value, props.suggestedCountry || undefined))
    : null;
const country = ref(initial?.country ?? (props.suggestedCountry || guessCountry()));
const national = ref(initial ? initial.formatNational() : (model.value ?? ''));
const touched = ref(Boolean(model.value)); // un numéro déjà enregistré mais invalide est signalé tout de suite
const userPickedCountry = ref(Boolean(initial));

// Ancien numéro reconnu : on enregistre directement sa forme internationale.
if (initial && model.value !== initial.number) {
    model.value = initial.number;
}

const current = computed(() => countries.find((c) => c.iso === country.value));
const parsed = computed(() => (national.value ? parsePhoneNumberFromString(national.value, country.value) : null));
const isValid = computed(() => Boolean(parsed.value?.isValid()));

const placeholder = computed(() => {
    const example = getExampleNumber(country.value, examples);
    return example ? example.formatNational() : '';
});

const localError = computed(() => {
    if (!touched.value || !national.value || isValid.value) return '';
    return t('phone.invalid', { country: current.value?.name ?? '', example: placeholder.value });
});

const shownError = computed(() => props.error || localError.value);

// Le pays de résidence choisi plus haut devient l'indicatif, tant que la personne ne l'a pas changé elle-même.
watch(() => props.suggestedCountry, (iso) => {
    if (iso && !userPickedCountry.value && countries.some((c) => c.iso === iso)) {
        country.value = iso;
        reformatAndEmit();
    }
});

const emitValue = () => {
    if (!national.value.trim()) {
        model.value = '';
    } else if (parsed.value) {
        model.value = parsed.value.number; // E.164
    } else {
        model.value = `+${current.value?.dial ?? ''}${national.value.replace(/\D/g, '')}`;
    }
};

const onInput = (event) => {
    let value = event.target.value;
    // Numéro collé au format international : on en déduit le pays.
    if (value.trim().startsWith('+')) {
        const intl = parsePhoneNumberFromString(value);
        if (intl?.country) {
            country.value = intl.country;
            userPickedCountry.value = true;
            value = intl.formatNational();
        }
    }
    national.value = new AsYouType(country.value).input(value);
    emitValue();
};

// Remet en forme le numéro déjà saisi selon le nouveau pays et met à jour la valeur envoyée.
const reformatAndEmit = () => {
    if (national.value) {
        national.value = new AsYouType(country.value).input(national.value.replace(/[^\d]/g, ''));
    }
    emitValue();
};

const onCountryChange = () => {
    userPickedCountry.value = true;
    reformatAndEmit();
};

const describedBy = computed(() => `${props.id}-help`);
</script>

<template>
    <div class="space-y-2">
        <label :for="id" class="block text-sm font-semibold text-hsi-navy">{{ label }}<span v-if="!required" class="font-normal text-hsi-slate"> ({{ t('common.optional') }})</span></label>

        <div
            class="flex h-[52px] w-full items-stretch overflow-hidden rounded-2xl border bg-[#F4F6FA] transition focus-within:bg-white focus-within:ring-4"
            :class="shownError
                ? 'border-hsi-danger/60 focus-within:border-hsi-danger focus-within:ring-hsi-danger/15'
                : 'border-transparent hover:border-hsi-border focus-within:border-hsi-gold focus-within:ring-hsi-gold/20'"
        >
            <div class="relative flex shrink-0 items-center border-r border-hsi-border/80">
                <span class="pointer-events-none flex items-center gap-1.5 pl-3.5 pr-7 text-[15px] text-hsi-navy" aria-hidden="true">
                    <span class="text-lg leading-none">{{ current?.flag }}</span>
                    <span class="font-medium tabular-nums">+{{ current?.dial }}</span>
                </span>
                <ChevronDown class="pointer-events-none absolute right-2 h-4 w-4 text-hsi-slate" aria-hidden="true" />
                <select
                    :id="`${id}-country`"
                    v-model="country"
                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                    :aria-label="t('phone.dialLabel', { country: current?.name, dial: current?.dial })"
                    @change="onCountryChange"
                >
                    <option v-for="c in countries" :key="c.iso" :value="c.iso">{{ c.flag }} {{ c.name }} (+{{ c.dial }})</option>
                </select>
            </div>
            <input
                :id="id"
                :value="national"
                type="tel"
                inputmode="tel"
                autocomplete="tel-national"
                :placeholder="placeholder"
                :required="required"
                :aria-invalid="shownError ? 'true' : 'false'"
                :aria-describedby="describedBy"
                class="min-w-0 flex-1 border-0 bg-transparent px-4 text-[15px] text-hsi-navy placeholder:text-hsi-slate-light focus:outline-none focus:ring-0"
                @input="onInput"
                @blur="touched = true"
            >
            <span v-if="isValid" class="flex items-center pr-4 text-hsi-success" aria-hidden="true">
                <CheckCircle2 class="h-5 w-5" />
            </span>
        </div>

        <p v-if="shownError" :id="describedBy" class="flex items-start gap-1.5 text-[13px] font-medium text-hsi-danger" role="alert">
            <AlertCircle class="mt-px h-4 w-4 shrink-0" aria-hidden="true" />
            <span>{{ shownError }}</span>
        </p>
        <p v-else :id="describedBy" class="text-[13px] text-hsi-slate">
            {{ t('phone.help') }}
        </p>
    </div>
</template>
