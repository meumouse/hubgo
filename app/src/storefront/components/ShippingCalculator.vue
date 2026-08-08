<script setup>
/**
 * ShippingCalculator.vue — the storefront card.
 *
 * Two states:
 *   - No postcode yet: the free-shipping badge, the title and the CEP form.
 *   - Quoted: the badge, the headline for the preferred (or cheapest) method
 *     and a link opening the full option list.
 *
 * Everything visual comes from CSS custom properties (see calculator.css), so
 * the settings panel and Elementor both style this component without it holding
 * any knowledge of either.
 *
 * @since 3.1.0
 */
import { computed, onMounted, ref } from 'vue';
import CepForm from './CepForm.vue';
import CepFinderModal from './CepFinderModal.vue';
import ShippingOptionsModal from './ShippingOptionsModal.vue';
import { useShippingQuote } from '../useShippingQuote';
import { formatCep, interpolate } from '../format';
import { getAddressLookup } from '../params';
import { __ } from '../../utils/i18n';

const props = defineProps({
    config: { type: Object, required: true },
});

const rootEl = ref( null );
const optionsOpen = ref( false );
const finderOpen = ref( false );

const {
    postcode,
    preferredId,
    preferredRate,
    rates,
    freeShipping,
    context,
    loading,
    error,
    hasQuoted,
    isPreferenceEnabled,
    calculate,
    selectMethod,
    clearPreference,
} = useShippingQuote( props.config );

const texts = computed( () => props.config.texts || {} );
const features = computed( () => props.config.features || {} );

const showFinder = computed(
    () => Boolean( features.value.finder ) && Boolean( texts.value.finderLink ) && Boolean( getAddressLookup().enabled ),
);

/**
 * Badge copy: the "already free" variant wins over the threshold pitch.
 *
 * Hidden entirely when there is no threshold to advertise and no free rate on
 * offer — a badge promising nothing is worse than no badge.
 */
const badgeText = computed( () => {
    if ( ! features.value.badge ) {
        return '';
    }

    const free = freeShipping.value || {};

    if ( free.qualifies || free.has_free_rate ) {
        return texts.value.freeShippingActive || '';
    }

    if ( free.enabled && free.threshold_formatted ) {
        return interpolate( texts.value.freeShippingBadge || '', free.threshold_formatted );
    }

    // Before the first quote there is no zone to read a threshold from, so fall
    // back to the configured one when the store set it manually.
    if ( ! hasQuoted.value && props.config.freeShippingHint ) {
        return interpolate( texts.value.freeShippingBadge || '', props.config.freeShippingHint );
    }

    return '';
} );

/**
 * Headline for the preferred method.
 *
 * A free delivery gets its own sentence built from the localized date, rather
 * than a search-and-replace over the already-translated "Receba até …" — that
 * trick only works in the language it was written for.
 */
const headline = computed( () => {
    const rate = preferredRate.value;

    if ( ! rate ) {
        return '';
    }

    const delivery = rate.delivery || {};

    if ( rate.is_free ) {
        return delivery.date_label
            ? interpolate( __( 'Chegará grátis até %s' ), delivery.date_label )
            : __( 'Chegará grátis' );
    }

    return delivery.headline || interpolate( __( 'Entrega via %s' ), rate.label );
} );

const metaLine = computed( () => {
    const rate = preferredRate.value;

    if ( ! rate ) {
        return '';
    }

    const price = rate.is_free ? __( 'Grátis' ) : rate.cost_formatted;
    const destination = interpolate( __( 'para o CEP %s' ), formatCep( postcode.value ) );

    return [ rate.label, price, destination ].join( ' · ' );
} );

const destination = computed( () => ( context.value && context.value.state ) ? String( context.value.state ) : '' );

onMounted( () => {
    // A returning shopper already has a postcode in the cookie: quote it up
    // front so the card lands populated instead of asking again.
    if ( postcode.value ) {
        calculate( postcode.value );
    }
} );

/**
 * Fill the field from the address finder and quote it immediately.
 *
 * @param {string} digits Eight-digit postcode.
 * @return {void}
 */
function handleCepFound( digits ) {
    calculate( digits );
}
</script>

<template>
    <div ref="rootEl">
        <div v-if="badgeText" class="hubgo-calc__badge">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 6h11v9H3zm12 3h3.5l2.5 3v3h-6zM6.5 20a1.8 1.8 0 1 1 0-3.6 1.8 1.8 0 0 1 0 3.6m11 0a1.8 1.8 0 1 1 0-3.6 1.8 1.8 0 0 1 0 3.6" />
            </svg>
            {{ badgeText }}
        </div>

        <div v-if="texts.title" class="hubgo-calc__title">{{ texts.title }}</div>
        <div v-if="texts.info" class="hubgo-calc__info">{{ texts.info }}</div>

        <!-- Empty state: no postcode known yet. -->
        <template v-if="! postcode">
            <CepForm
                :model-value="''"
                :loading="loading"
                :placeholder="texts.placeholder || ''"
                :button-label="texts.button || __( 'Calcular' )"
                :auto="Boolean( features.auto )"
                @submit="calculate"
            />

            <p v-if="error" class="hubgo-calc__error">{{ error }}</p>
        </template>

        <!-- Quoted state. -->
        <template v-else>
            <div class="hubgo-calc__result">
                <div v-if="loading && ! preferredRate" class="hubgo-calc__loading">
                    <span class="hubgo-calc__spinner" aria-hidden="true" />
                    {{ interpolate( __( 'Calculando entrega para %s…' ), formatCep( postcode ) ) }}
                </div>

                <template v-else-if="preferredRate">
                    <div class="hubgo-calc__headline">{{ headline }}</div>
                    <div class="hubgo-calc__meta">{{ metaLine }}</div>
                </template>

                <p v-else-if="error" class="hubgo-calc__error">{{ error }}</p>

                <p v-else class="hubgo-calc__empty">
                    {{ interpolate( __( 'Nenhuma opção de entrega disponível para o CEP %s.' ), formatCep( postcode ) ) }}
                </p>
            </div>

            <button
                v-if="features.options && texts.moreOptions"
                type="button"
                class="hubgo-calc__more"
                @click="optionsOpen = true"
            >
                {{ texts.moreOptions }}
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6" /></svg>
            </button>

            <span v-if="texts.note && ! features.options" class="hubgo-calc__note">{{ texts.note }}</span>
        </template>

        <button
            v-if="showFinder"
            type="button"
            class="hubgo-calc__finder-link"
            @click="finderOpen = true"
        >
            {{ texts.finderLink }}
        </button>

        <ShippingOptionsModal
            :open="optionsOpen"
            :token-source="rootEl"
            :postcode="postcode"
            :destination="destination"
            :rates="rates"
            :preferred-id="preferredId"
            :loading="loading"
            :error="error"
            :texts="texts"
            :display="config.display || 'list'"
            :preference-enabled="isPreferenceEnabled"
            @close="optionsOpen = false"
            @submit-cep="calculate"
            @select="selectMethod"
            @clear-preference="clearPreference"
        />

        <CepFinderModal
            :open="finderOpen"
            :token-source="rootEl"
            @close="finderOpen = false"
            @found="handleCepFound"
        />
    </div>
</template>
