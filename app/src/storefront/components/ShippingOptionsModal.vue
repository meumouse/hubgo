<script setup>
/**
 * ShippingOptionsModal.vue — every delivery option, plus the CEP editor.
 *
 * Picking an option is what records the shopper's preference: the choice is
 * written to the cookie `Core\Shipping_Preference` reads at the checkout, so
 * the method they select here is the one pre-selected when they get there.
 *
 * The compact card owns the fetch and the state; this component is a renderer.
 *
 * @since 3.0.0
 */
import { computed, ref, watch } from 'vue';
import BaseModal from './BaseModal.vue';
import CepForm from './CepForm.vue';
import { formatCep } from '../format';
import { __ } from '../../utils/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    tokenSource: { type: [ Object, null ], default: null },
    postcode: { type: String, default: '' },
    /** Human-readable destination hint (the state resolved from the postcode). */
    destination: { type: String, default: '' },
    rates: { type: Array, default: () => [] },
    preferredId: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    texts: { type: Object, default: () => ({}) },
    display: { type: String, default: 'list' },
    preferenceEnabled: { type: Boolean, default: true },
});

const emit = defineEmits([ 'close', 'submit-cep', 'select', 'clear-preference' ]);

const editingCep = ref( ! props.postcode );

watch( () => props.open, ( isOpen ) => {
    if ( isOpen ) {
        editingCep.value = ! props.postcode;
    }
} );

// A fresh quote for a new postcode means the chip can come back.
watch( () => props.postcode, ( value ) => {
    if ( value ) {
        editingCep.value = false;
    }
} );

const isTable = computed( () => props.display === 'table' );
const hasPreference = computed( () => props.preferenceEnabled && Boolean( props.preferredId ) );

/**
 * Record the shopper's pick.
 *
 * @param {object} rate Rate row.
 * @return {void}
 */
function choose( rate ) {
    if ( ! props.preferenceEnabled ) {
        return;
    }

    emit( 'select', rate.id );
}

/**
 * Forward a new postcode to the parent.
 *
 * @param {string} value Masked postcode.
 * @return {void}
 */
function submitCep( value ) {
    emit( 'submit-cep', value );
}
</script>

<template>
    <BaseModal
        :open="open"
        :token-source="tokenSource"
        :title="__( 'Opções de entrega' )"
        @close="emit( 'close' )"
    >
        <div class="hubgo-calc-modal__section">
            <div class="hubgo-calc-modal__section-title">
                {{ __( 'Calculamos os custos e prazos para este endereço:' ) }}
            </div>

            <CepForm
                v-if="editingCep"
                :model-value="postcode"
                :loading="loading"
                :placeholder="texts.placeholder || ''"
                :button-label="texts.button || __( 'Calcular' )"
                @submit="submitCep"
            />

            <div v-else class="hubgo-calc__destination">
                <svg class="hubgo-calc__pin" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" />
                    <circle cx="12" cy="10" r="3" />
                </svg>

                <div class="hubgo-calc__destination-body">
                    <div>
                        {{ __( 'CEP:' ) }} <strong>{{ formatCep( postcode ) }}</strong>
                        <template v-if="destination"> &middot; {{ destination }}</template>
                    </div>

                    <button type="button" class="hubgo-calc__destination-edit" @click="editingCep = true">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 20h9" />
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                        </svg>
                        {{ __( 'Escolher outro endereço' ) }}
                    </button>
                </div>
            </div>

            <p v-if="error" class="hubgo-calc__error">{{ error }}</p>
        </div>

        <div class="hubgo-calc-modal__section">
            <div v-if="loading && ! rates.length" class="hubgo-calc__loading">
                <span class="hubgo-calc__spinner" aria-hidden="true" />
                {{ __( 'Calculando opções de entrega…' ) }}
            </div>

            <p v-else-if="! rates.length && ! error" class="hubgo-calc__empty">
                {{ __( 'Nenhuma forma de entrega disponível para este CEP.' ) }}
            </p>

            <template v-else-if="rates.length">
                <div class="hubgo-calc-modal__section-title">{{ __( 'Receber compra' ) }}</div>

                <table v-if="isTable" class="hubgo-calc__table">
                    <tbody>
                        <tr v-if="texts.headerShip || texts.headerValue">
                            <th>{{ texts.headerShip }}</th>
                            <th>{{ texts.headerValue }}</th>
                        </tr>

                        <tr
                            v-for="rate in rates"
                            :key="rate.id"
                            :class="{ 'is-selected': rate.id === preferredId }"
                            :style="preferenceEnabled ? 'cursor: pointer' : null"
                            @click="choose( rate )"
                        >
                            <td>
                                <span class="hubgo-calc__option-label">{{ rate.label }}</span>
                                <span v-if="rate.delivery && rate.delivery.headline" class="hubgo-calc__option-delivery">
                                    {{ rate.delivery.headline }}
                                </span>
                            </td>
                            <td>{{ rate.is_free ? __( 'Grátis' ) : rate.cost_formatted }}</td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="hubgo-calc__options">
                    <button
                        v-for="rate in rates"
                        :key="rate.id"
                        type="button"
                        class="hubgo-calc__option"
                        :class="{ 'is-selected': rate.id === preferredId }"
                        :aria-pressed="rate.id === preferredId"
                        @click="choose( rate )"
                    >
                        <span class="hubgo-calc__option-radio" aria-hidden="true" />

                        <span class="hubgo-calc__option-body">
                            <span class="hubgo-calc__option-label">{{ rate.label }}</span>
                            <span v-if="rate.delivery && rate.delivery.headline" class="hubgo-calc__option-delivery">
                                {{ rate.delivery.headline }}
                            </span>
                        </span>

                        <span class="hubgo-calc__option-price">
                            {{ rate.is_free ? __( 'Grátis' ) : rate.cost_formatted }}
                        </span>
                    </button>
                </div>

                <p v-if="preferenceEnabled && hasPreference && texts.preferenceSaved" class="hubgo-calc__pref-saved">
                    {{ texts.preferenceSaved }}

                    <button
                        v-if="texts.clearPreference"
                        type="button"
                        class="hubgo-calc__clear-pref"
                        @click="emit( 'clear-preference' )"
                    >
                        {{ texts.clearPreference }}
                    </button>
                </p>

                <p v-else-if="preferenceEnabled && texts.preferenceHint" class="hubgo-calc__pref-hint">
                    {{ texts.preferenceHint }}
                </p>

                <span v-if="texts.note" class="hubgo-calc__note">{{ texts.note }}</span>
            </template>
        </div>
    </BaseModal>
</template>
