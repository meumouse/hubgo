<script setup>
/**
 * CepFinderModal.vue — "Não sei meu CEP".
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
 * @since 3.1.0
 */
import { computed, ref, watch } from 'vue';
import BaseModal from './BaseModal.vue';
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
        error.value = requestError.message || __( 'Não foi possível buscar endereços.' );
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
        error.value = __( 'Informe pelo menos 3 letras da cidade e da rua.' );

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
            error.value = __( 'Esse endereço não tem um CEP exato. Tente incluir o número e a rua.' );

            return;
        }

        emit( 'found', postcode );

        // Rotate the token so a follow-up search opens a fresh billing session.
        session = newSessionToken();
        emit( 'close' );
    } catch ( requestError ) {
        error.value = requestError.message || __( 'Não foi possível obter o CEP.' );
    } finally {
        resolvingId.value = '';
    }
}
</script>

<template>
    <BaseModal
        :open="open"
        :token-source="tokenSource"
        :title="__( 'Descobrir meu CEP' )"
        :description="isFreetext
            ? __( 'Digite seu endereço e selecione a opção correta.' )
            : __( 'Informe o estado, a cidade e a rua para encontrar o CEP.' )"
        @close="emit( 'close' )"
    >
        <form v-if="isFreetext" @submit.prevent="runSearch">
            <input
                v-model="query"
                type="text"
                class="hubgo-calc__finder-field"
                :placeholder="__( 'Rua, número, cidade…' )"
                :aria-label="__( 'Endereço' )"
            >
        </form>

        <form v-else class="hubgo-calc__finder-grid" @submit.prevent="submitStructured">
            <select v-model="uf" class="hubgo-calc__finder-field" :aria-label="__( 'Estado' )">
                <option value="">{{ __( 'UF' ) }}</option>
                <option v-for="state in lookup.states" :key="state.value" :value="state.value">
                    {{ state.value }}
                </option>
            </select>

            <input
                v-model="city"
                type="text"
                class="hubgo-calc__finder-field"
                :placeholder="__( 'Cidade' )"
                :aria-label="__( 'Cidade' )"
            >

            <input
                v-model="street"
                type="text"
                class="hubgo-calc__finder-field hubgo-calc__input--full"
                :placeholder="__( 'Rua ou avenida' )"
                :aria-label="__( 'Rua' )"
            >

            <button type="submit" class="hubgo-calc__button hubgo-calc__input--full" :disabled="searching">
                <span v-if="searching" class="hubgo-calc__spinner" aria-hidden="true" />
                <span v-else>{{ __( 'Buscar endereço' ) }}</span>
            </button>
        </form>

        <p v-if="error" class="hubgo-calc__error">{{ error }}</p>

        <div v-if="searching && isFreetext" class="hubgo-calc__loading" style="margin-top: 10px">
            <span class="hubgo-calc__spinner" aria-hidden="true" />
            {{ __( 'Buscando endereços…' ) }}
        </div>

        <div v-if="suggestions.length" class="hubgo-calc__suggestions">
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

        <p v-else-if="searched && ! searching && ! error" class="hubgo-calc__hint">
            {{ __( 'Nenhum endereço encontrado. Tente ser mais específico.' ) }}
        </p>
    </BaseModal>
</template>
