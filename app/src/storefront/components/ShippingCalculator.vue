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
 * @since 3.0.0
 * @version 3.1.0
 */
import { computed, onMounted, ref } from 'vue';
import { ChevronRight, Truck } from '@boxicons/vue';
import CepForm from './CepForm.vue';
import ShippingOptionsModal from './ShippingOptionsModal.vue';
import { useShippingQuote } from '../useShippingQuote';
import { formatCep, interpolate } from '../format';
import { __ } from '../../utils/i18n';

const props = defineProps({
    config: { type: Object, required: true },
});

const rootEl = ref( null );
const optionsOpen = ref( false );

const {
    postcode,
    preferredId,
    preferredRate,
    rates,
    freeShipping,
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
            ? interpolate( __( 'Arrives free by %s' ), delivery.date_label )
            : __( 'Arrives free' );
    }

    return delivery.headline || interpolate( __( 'Delivery via %s' ), rate.label );
} );

const metaLine = computed( () => {
    const rate = preferredRate.value;

    if ( ! rate ) {
        return '';
    }

    const price = rate.is_free ? __( 'Free' ) : rate.cost_formatted;
    const destination = interpolate( __( 'to postcode %s' ), formatCep( postcode.value ) );

    return [ rate.label, price, destination ].join( ' · ' );
} );

onMounted( () => {
    // A returning shopper already has a postcode in the cookie: quote it up
    // front so the card lands populated instead of asking again.
    if ( postcode.value ) {
        calculate( postcode.value );
    }
} );
</script>

<template>
    <div ref="rootEl">
        <div v-if="badgeText" class="hubgo-calc__badge">
            <Truck aria-hidden="true" />
            {{ badgeText }}
        </div>

        <!--
            The two states are wrapped in a real element rather than a <template>
            because a fragment has no node for a transition to act on. Nothing in
            calculator.css selects these by position, so the extra div is inert.

            The title and the intro live inside the empty state on purpose: both
            invite the shopper to type a postcode, and repeating the invitation
            above an answer they already have pushes that answer down the card.
            Being inside the transition is also what makes them leave with the
            form instead of vanishing on their own.
        -->
        <Transition name="hubgo-calc-fade" mode="out-in">
            <!-- Empty state: no postcode known yet. -->
            <div v-if="! postcode" key="empty">
                <div v-if="texts.title" class="hubgo-calc__title">{{ texts.title }}</div>
                <div v-if="texts.info" class="hubgo-calc__info">{{ texts.info }}</div>

                <CepForm
                    :model-value="''"
                    :loading="loading"
                    :placeholder="texts.placeholder || ''"
                    :button-label="texts.button || __( 'Calculate' )"
                    :auto="Boolean( features.auto )"
                    @submit="calculate"
                />

                <p v-if="error" class="hubgo-calc__error">{{ error }}</p>
            </div>

            <!-- Quoted state. -->
            <div v-else key="quoted">
                <div class="hubgo-calc__result">
                    <Transition name="hubgo-calc-fade" mode="out-in">
                        <div v-if="loading && ! preferredRate" key="loading" class="hubgo-calc__loading">
                            <span class="hubgo-calc__spinner" aria-hidden="true" />
                            {{ interpolate( __( 'Calculating delivery for %s…' ), formatCep( postcode ) ) }}
                        </div>

                        <div v-else-if="preferredRate" key="rate">
                            <div class="hubgo-calc__headline">{{ headline }}</div>
                            <div class="hubgo-calc__meta">{{ metaLine }}</div>
                        </div>

                        <p v-else-if="error" key="error" class="hubgo-calc__error">{{ error }}</p>

                        <p v-else key="none" class="hubgo-calc__empty">
                            {{ interpolate( __( 'No delivery option available for postcode %s.' ), formatCep( postcode ) ) }}
                        </p>
                    </Transition>
                </div>

                <button
                    v-if="features.options && texts.moreOptions"
                    type="button"
                    class="hubgo-calc__more"
                    @click="optionsOpen = true"
                >
                    {{ texts.moreOptions }}
                    <ChevronRight aria-hidden="true" />
                </button>

                <span v-if="texts.note && ! features.options" class="hubgo-calc__note">{{ texts.note }}</span>
            </div>
        </Transition>

        <ShippingOptionsModal
            :open="optionsOpen"
            :token-source="rootEl"
            :postcode="postcode"
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
    </div>
</template>
