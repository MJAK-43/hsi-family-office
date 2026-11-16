import { ref } from 'vue';

/**
 * Devise du fonds (paramètre d'administration), partagée par le serveur à chaque page.
 * Les libellés écrits avec « $ » dans les gabarits sont adaptés automatiquement (voir i18n/tr).
 */
export const activeCurrency = ref({ code: 'USD', symbol: '$', decimals: 2 });

export function setActiveCurrency(value) {
    if (value?.code) activeCurrency.value = value;
}

export const currencySymbol = () => activeCurrency.value.symbol;

/** Remplace « $ » par le symbole de la devise active : « Montant ($) » → « Montant (€) ». */
export function withCurrency(text) {
    const symbol = activeCurrency.value.symbol;
    if (symbol === '$' || typeof text !== 'string' || !text.includes('$')) return text;
    // « $75 » (anglais) → « 75 € » ; « 75 $ », « ($) » → symbole remplacé.
    return text.replace(/\$(\d[\d\s.,]*)/g, `$1 ${symbol}`).replace(/\$/g, symbol);
}

/** Montant formaté dans la devise active : 1 234,5 → « 1 234,5 € ». */
export function formatMoney(value, { decimals, locale = 'fr-FR' } = {}) {
    const max = decimals ?? activeCurrency.value.decimals;
    const number = Number(value ?? 0).toLocaleString(locale, { minimumFractionDigits: 0, maximumFractionDigits: max });
    return `${number} ${activeCurrency.value.symbol}`;
}
