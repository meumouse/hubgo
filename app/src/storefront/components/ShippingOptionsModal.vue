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
 * @version 3.1.0
 */
import { computed, ref, watch } from 'vue';
import { Location, Pencil } from '@boxicons/vue';
import BaseModal from './BaseModal.vue';
import CepForm from './CepForm.vue';
import { formatCep, interpolate } from '../format';
import { __ } from '../../utils/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    tokenSource: { type: [ Object, null ], default: null },
    postcode: { type: String, default: '' },
    /** Destination address resolved for the postcode; every key may be empty. */
    address: { type: Object, default: () => ({}) },
    rates: { type: Array, default: () => [] },
    preferredId: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    stale: { type: Boolean, default: false },
    error: { type: String, default: '' },
    texts: { type: Object, default: () => ({}) },
    display: { type: String, default: 'list' },
    preferenceEnabled: { type: Boolean, default: true },
    finderEnabled: { type: Boolean, default: false },
});

const emit = defineEmits([ 'close', 'submit-cep', 'select', 'clear-preference', 'open-finder' ]);

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
        :title="__( 'Delivery options' )"
        @close="emit( 'close' )"
    >
        <div class="hubgo-calc-modal__section">
            <div class="hubgo-calc-modal__section-title">
                {{ __( 'We calculated the costs and delivery times for this address:' ) }}
            </div>

            <Transition name="hubgo-calc-fade" mode="out-in">
                <CepForm
                    v-if="editingCep"
                    key="form"
                    :model-value="postcode"
                    :loading="loading"
                    :placeholder="texts.placeholder || ''"
                    :button-label="texts.button || __( 'Calculate' )"
                    :finder="finderEnabled"
                    :finder-label="texts.cepFinder || ''"
                    @submit="submitCep"
                    @open-finder="emit( 'open-finder' )"
                />

                <div v-else key="chip" class="hubgo-calc__destination">
                    <Location class="hubgo-calc__pin" aria-hidden="true" />

                    <div class="hubgo-calc__destination-body">
                        <!--
                            With the street resolved the postcode drops to the
                            supporting line: the shopper typed the digits, so
                            what they are checking here is whether we understood
                            which address they meant.
                        -->
                        <template v-if="address.formatted">
                            <div class="hubgo-calc__destination-address">{{ address.formatted }}</div>
                            <div class="hubgo-calc__destination-meta">
                                {{ interpolate( __( 'Postcode %s' ), formatCep( postcode ) ) }}
                            </div>
                        </template>

                        <div v-else>
                            {{ __( 'Postcode:' ) }} <strong>{{ formatCep( postcode ) }}</strong>
                        </div>

                        <button type="button" class="hubgo-calc__destination-edit" @click="editingCep = true">
                            <Pencil aria-hidden="true" />
                            {{ __( 'Choose another address' ) }}
                        </button>
                    </div>
                </div>
            </Transition>

            <Transition name="hubgo-calc-fade">
                <p v-if="error" class="hubgo-calc__error">{{ error }}</p>
            </Transition>
        </div>

        <div class="hubgo-calc-modal__section">
            <Transition name="hubgo-calc-fade" mode="out-in">
            <div v-if="loading && ( stale || ! rates.length )" key="loading" class="hubgo-calc__loading">
                <span class="hubgo-calc__spinner" aria-hidden="true" />
                {{ __( 'Calculating delivery options…' ) }}
            </div>

            <p v-else-if="! rates.length && ! error" key="empty" class="hubgo-calc__empty">
                {{ __( 'No delivery method available for this postcode.' ) }}
            </p>

            <div v-else-if="rates.length" key="rates">
                <div class="hubgo-calc-modal__section-title">{{ __( 'Receive the order' ) }}</div>

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
                            <td>{{ rate.is_free ? __( 'Free' ) : rate.cost_formatted }}</td>
                        </tr>
                    </tbody>
                </table>

                <!--
                    The rows land one after the other. The delay is a custom
                    property, not an inline `transition-delay`: the latter would
                    survive the entry and postpone every later hover and
                    selection change on that row by the same amount.
                -->
                <TransitionGroup v-else tag="div" name="hubgo-calc-option" class="hubgo-calc__options">
                    <button
                        v-for="( rate, index ) in rates"
                        :key="rate.id"
                        type="button"
                        class="hubgo-calc__option"
                        :class="{ 'is-selected': rate.id === preferredId }"
                        :style="{ '--hubgo-calc-stagger': `${ index * 40 }ms` }"
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
                            {{ rate.is_free ? __( 'Free' ) : rate.cost_formatted }}
                        </span>
                    </button>
                </TransitionGroup>

                <Transition name="hubgo-calc-fade" mode="out-in">
                    <p
                        v-if="preferenceEnabled && hasPreference && texts.preferenceSaved"
                        key="saved"
                        class="hubgo-calc__pref-saved"
                    >
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

                    <p
                        v-else-if="preferenceEnabled && texts.preferenceHint"
                        key="hint"
                        class="hubgo-calc__pref-hint"
                    >
                        {{ texts.preferenceHint }}
                    </p>
                </Transition>

                <span v-if="texts.note" class="hubgo-calc__note">{{ texts.note }}</span>
            </div>
            </Transition>
        </div>
    </BaseModal>
</template>
