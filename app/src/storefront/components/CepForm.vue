<script setup>
/**
 * CepForm.vue — masked postcode input plus the submit button.
 *
 * Shared by the compact card and the options modal so the two can never drift
 * apart in validation or masking behaviour.
 *
 * @since 3.0.0
 */
import { ref, watch } from 'vue';
import { formatCep, isCompleteCep } from '../format';
import { __ } from '../../utils/i18n';

const props = defineProps({
    modelValue: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    buttonLabel: { type: String, default: '' },
    /** Recalculate as soon as the eight digits are complete. */
    auto: { type: Boolean, default: false },
});

const emit = defineEmits([ 'submit' ]);

const value = ref( formatCep( props.modelValue ) );
const localError = ref( '' );

watch( () => props.modelValue, ( next ) => {
    value.value = formatCep( next );
} );

/**
 * Mask the input and, in auto mode, fire as soon as it is complete.
 *
 * @param {string} raw Raw input value.
 * @return {void}
 */
function handleInput( raw ) {
    value.value = formatCep( raw );
    localError.value = '';

    if ( props.auto && isCompleteCep( value.value ) ) {
        emit( 'submit', value.value );
    }
}

/**
 * Validate and submit.
 *
 * @return {void}
 */
function submit() {
    if ( ! isCompleteCep( value.value ) ) {
        localError.value = __( 'Digite um CEP válido' );

        return;
    }

    localError.value = '';
    emit( 'submit', value.value );
}
</script>

<template>
    <div>
        <form class="hubgo-calc__form" @submit.prevent="submit">
            <input
                :value="value"
                type="text"
                inputmode="numeric"
                autocomplete="postal-code"
                maxlength="9"
                class="hubgo-calc__input"
                :placeholder="placeholder"
                :aria-label="placeholder || __( 'CEP' )"
                @input="handleInput( $event.target.value )"
            >

            <button type="submit" class="hubgo-calc__button" :disabled="loading">
                <span v-if="loading" class="hubgo-calc__spinner" aria-hidden="true" />
                <span v-else>{{ buttonLabel }}</span>
            </button>
        </form>

        <p v-if="localError" class="hubgo-calc__error">{{ localError }}</p>
    </div>
</template>
