<script setup>
/**
 * CepFinderModal.vue — "I do not know my postcode".
 *
 * Serves both lookup providers from one component. The server tells us which
 * form to render through `address_lookup.mode`:
 *
 *   - `freetext` (Google Places): one input, debounced, with a session token
 *     tying the keystrokes to the final resolution so Google bills the search
 *     once instead of once per character.
 *   - `structured` (ViaCEP): state + city + street, submitted explicitly.
 *     ViaCEP has no free-text endpoint, and it returns the postcode inline —
 *     so picking a suggestion resolves without a second round-trip.
 *
 * @since 3.0.0
 * @version 3.1.0
 */
import { computed, ref, watch } from 'vue';
import BaseModal from './BaseModal.vue';
import CalcSelect from './CalcSelect.vue';
import { storefrontApi } from '../api';
import { getAddressLookup } from '../params';
import { __ } from '../../utils/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    tokenSource: { type: [ Object, null ], default: null },
});

const emit = defineEmits([ 'close', 'found' ]);

const DEBOUNCE_MS = 350;

const lookup = getAddressLookup();
const isFreetext = computed( () => lookup.mode === 'freetext' );

/**
 * The WooCommerce state list, shaped for the custom select.
 *
 * `Core\Address_Lookup::get_states()` mirrors `WC()->countries->get_states()`,
 * so the label is the state name and the value is the code the provider wants.
 * Only the name is rendered: `CalcSelect` matches the value as well as the
 * label, so typing either "sao" or "sp" still finds São Paulo without spending
 * a row on a two-letter code.
 *
 * @return {Array<object>}
 */
const stateOptions = computed( () => ( Array.isArray( lookup.states ) ? lookup.states : [] ).map( ( state ) => {
    const value = String( state.value ?? '' );

    return {
        value,
        label: String( state.label ?? '' ) || value,
    };
} ) );

const query = ref( '' );
const uf = ref( '' );
const city = ref( '' );
const street = ref( '' );
const suggestions = ref( [] );
const searching = ref( false );
const resolvingId = ref( '' );
const error = ref( '' );
const searched = ref( false );

let session = '';
let debounceHandle = null;
let controller = null;

watch( () => props.open, ( isOpen ) => {
    if ( ! isOpen ) {
        return;
    }

    session = newSessionToken();
    query.value = '';
    uf.value = '';
    city.value = '';
    street.value = '';
    suggestions.value = [];
    searching.value = false;
    resolvingId.value = '';
    error.value = '';
    searched.value = false;
} );

// Free-text mode only: the structured form is submitted explicitly, so
// debouncing it would just delay a button press.
watch( query, ( value ) => {
    if ( ! isFreetext.value ) {
        return;
    }

    window.clearTimeout( debounceHandle );

    if ( value.trim().length < 3 ) {
        suggestions.value = [];
        searching.value = false;
        abortPending();

        return;
    }

    searching.value = true;
    debounceHandle = window.setTimeout( () => runSearch(), DEBOUNCE_MS );
} );

/**
 * Mint a per-search identifier for the provider's session billing.
 *
 * @return {string}
 */
function newSessionToken() {
    try {
        if ( typeof crypto !== 'undefined' && crypto.randomUUID ) {
            return crypto.randomUUID();
        }
    } catch ( err ) {
        // Fall through to the manual token below.
    }

    return `s-${ Date.now() }-${ Math.round( Math.random() * 1e9 ) }`;
}

/**
 * Abort an in-flight search.
 *
 * @return {void}
 */
function abortPending() {
    if ( controller ) {
        controller.abort();
        controller = null;
    }
}

/**
 * Query the provider.
 *
 * @return {Promise<void>}
 */
async function runSearch() {
    abortPending();
    controller = new AbortController();

    searching.value = true;
    error.value = '';

    try {
        const payload = await storefrontApi.searchAddress( {
            q: isFreetext.value ? query.value.trim() : '',
            uf: uf.value,
            city: city.value.trim(),
            street: street.value.trim(),
            session,
        }, controller.signal );

        suggestions.value = Array.isArray( payload.suggestions ) ? payload.suggestions : [];
        searched.value = true;
    } catch ( requestError ) {
        if ( requestError.name === 'AbortError' ) {
            return;
        }

        suggestions.value = [];
        searched.value = true;
        error.value = requestError.message || __( 'Could not search addresses.' );
    } finally {
        searching.value = false;
        controller = null;
    }
}

/**
 * Submit the structured form.
 *
 * @return {void}
 */
function submitStructured() {
    if ( ! uf.value || city.value.trim().length < 3 || street.value.trim().length < 3 ) {
        error.value = __( 'Enter at least 3 letters of the city and of the street.' );

        return;
    }

    runSearch();
}

/**
 * Resolve the picked suggestion into a postcode and hand it back.
 *
 * @param {object} suggestion Suggestion row.
 * @return {Promise<void>}
 */
async function pick( suggestion ) {
    error.value = '';

    // Providers that already knew the postcode skip the resolve round-trip.
    if ( suggestion.postcode ) {
        emit( 'found', suggestion.postcode );
        emit( 'close' );

        return;
    }

    resolvingId.value = suggestion.id;

    try {
        const payload = await storefrontApi.resolveAddress( { id: suggestion.id, session } );
        const postcode = String( payload.postcode || '' );

        if ( postcode.length !== 8 ) {
            error.value = __( 'That address has no exact postcode. Try including the street and the number.' );

            return;
        }

        emit( 'found', postcode );

        // Rotate the token so a follow-up search opens a fresh billing session.
        session = newSessionToken();
        emit( 'close' );
    } catch ( requestError ) {
        error.value = requestError.message || __( 'Could not resolve the postcode.' );
    } finally {
        resolvingId.value = '';
    }
}
</script>

<template>
    <BaseModal
        :open="open"
        :token-source="tokenSource"
        :title="__( 'Find my postcode' )"
        :description="isFreetext
            ? __( 'Type your address and pick the right option.' )
            : __( 'Enter the state, the city and the street to find the postcode.' )"
        @close="emit( 'close' )"
    >
        <form v-if="isFreetext" @submit.prevent="runSearch">
            <input
                v-model="query"
                type="text"
                class="hubgo-calc__finder-field"
                :placeholder="__( 'Street, number, city…' )"
                :aria-label="__( 'Address' )"
            >
        </form>

        <form v-else class="hubgo-calc__finder-grid" @submit.prevent="submitStructured">
            <CalcSelect
                v-model="uf"
                :options="stateOptions"
                :placeholder="__( 'State' )"
                :search-placeholder="__( 'Search state…' )"
                :empty-label="__( 'No state found' )"
                :aria-label="__( 'State' )"
                :token-source="tokenSource"
            />

            <input
                v-model="city"
                type="text"
                class="hubgo-calc__finder-field"
                :placeholder="__( 'City' )"
                :aria-label="__( 'City' )"
            >

            <input
                v-model="street"
                type="text"
                class="hubgo-calc__finder-field hubgo-calc__input--full"
                :placeholder="__( 'Street or avenue' )"
                :aria-label="__( 'Street' )"
            >

            <button type="submit" class="hubgo-calc__button hubgo-calc__input--full" :disabled="searching">
                <span v-if="searching" class="hubgo-calc__spinner" aria-hidden="true" />
                <span v-else>{{ __( 'Search address' ) }}</span>
            </button>
        </form>

        <Transition name="hubgo-calc-fade">
            <p v-if="error" class="hubgo-calc__error">{{ error }}</p>
        </Transition>

        <Transition name="hubgo-calc-fade">
            <div v-if="searching && isFreetext" class="hubgo-calc__loading" style="margin-top: 10px">
                <span class="hubgo-calc__spinner" aria-hidden="true" />
                {{ __( 'Searching addresses…' ) }}
            </div>
        </Transition>

        <!--
            The result panel is faded as one block rather than row by row: in
            free-text mode it is rebuilt on every debounced keystroke, and
            animating each suggestion would turn a steady list into a flicker.
        -->
        <Transition name="hubgo-calc-fade" mode="out-in">
            <div v-if="suggestions.length" key="suggestions" class="hubgo-calc__suggestions">
                <button
                    v-for="suggestion in suggestions"
                    :key="suggestion.id"
                    type="button"
                    class="hubgo-calc__suggestion"
                    :disabled="resolvingId !== ''"
                    @click="pick( suggestion )"
                >
                    <span class="hubgo-calc__suggestion-primary">
                        {{ suggestion.primary || suggestion.postcode }}
                    </span>
                    <span v-if="suggestion.secondary" class="hubgo-calc__suggestion-secondary">
                        {{ suggestion.secondary }}
                    </span>
                </button>
            </div>

            <p v-else-if="searched && ! searching && ! error" key="none" class="hubgo-calc__hint">
                {{ __( 'No address found. Try being more specific.' ) }}
            </p>
        </Transition>
    </BaseModal>
</template>
