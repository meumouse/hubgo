<script setup>
/**
 * CepForm.vue — masked postcode input plus the submit button.
 *
 * Shared by the compact card and the options modal so the two can never drift
 * apart in validation or masking behaviour — including the way out for the
 * shopper who does not know their postcode, which is offered right where they
 * get stuck rather than only on the first screen.
 *
 * @since 3.0.0
 * @version 3.0.0
 */
import { ref, watch } from 'vue';
import { Search } from '@boxicons/vue';
import { formatCep, isCompleteCep } from '../format';
import { __ } from '../../utils/i18n';

const props = defineProps({
    modelValue: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    buttonLabel: { type: String, default: '' },
    /** Recalculate as soon as the eight digits are complete. */
    auto: { type: Boolean, default: false },
    /** Whether an address provider is available to power the finder. */
    finder: { type: Boolean, default: false },
    /** Link copy. Empty hides the link even when a provider is available. */
    finderLabel: { type: String, default: '' },
});

const emit = defineEmits([ 'submit', 'open-finder' ]);

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
        localError.value = __( 'Enter a valid postcode' );

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
                :aria-label="placeholder || __( 'Postcode' )"
                @input="handleInput( $event.target.value )"
            >

            <button type="submit" class="hubgo-calc__button" :disabled="loading">
                <span v-if="loading" class="hubgo-calc__spinner" aria-hidden="true" />
                <span v-else>{{ buttonLabel }}</span>
            </button>
        </form>

        <p v-if="localError" class="hubgo-calc__error">{{ localError }}</p>

        <button
            v-if="finder && finderLabel"
            type="button"
            class="hubgo-calc__finder-link"
            @click="emit( 'open-finder' )"
        >
            <Search aria-hidden="true" />
            {{ finderLabel }}
        </button>
    </div>
</template>
