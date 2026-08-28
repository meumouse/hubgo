<script setup>
/**
 * CepFinderModal.vue — "I do not know my postcode".
 *
 * A single debounced field: the shopper types their address, picks the right
 * match, and the postcode comes back to the calculator.
 *
 * The session token is the billing contract, not a detail. The provider charges
 * per *search*, not per keystroke, as long as every request carries the same
 * token and the search is closed by resolving one suggestion — so the token is
 * minted when the window opens, sent on every call, and rotated only after a
 * postcode has been handed back.
 *
 * The form rendered here is the `freetext` one. A provider that announces
 * another mode gets no finder at all (see `params.getAddressLookup`), which is
 * better than posting a query it cannot answer.
 *
 * @since 3.0.0
 */
import { ref, watch } from 'vue';
import BaseModal from './BaseModal.vue';
import { storefrontApi } from '../api';
import { __ } from '../../utils/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    tokenSource: { type: [ Object, null ], default: null },
});

const emit = defineEmits([ 'close', 'found' ]);

const DEBOUNCE_MS = 350;
const MIN_QUERY_LENGTH = 3;

const query = ref( '' );
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
        // A window that closed mid-search must not resolve into the next one.
        window.clearTimeout( debounceHandle );
        abortPending();

        return;
    }

    session = newSessionToken();
    query.value = '';
    suggestions.value = [];
    searching.value = false;
    resolvingId.value = '';
    error.value = '';
    searched.value = false;
} );

watch( query, ( value ) => {
    window.clearTimeout( debounceHandle );

    if ( value.trim().length < MIN_QUERY_LENGTH ) {
        suggestions.value = [];
        searching.value = false;
        abortPending();

        return;
    }

    // The spinner goes up before the debounce elapses, not after: the shopper
    // stopped typing because they expect something to happen.
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

    const request = new AbortController();
    controller = request;

    searching.value = true;
    error.value = '';

    try {
        const payload = await storefrontApi.searchAddress( {
            q: query.value.trim(),
            session,
        }, request.signal );

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
        // Only the request that still owns the slot may clear the spinner: an
        // aborted one has already been replaced, and its spinner belongs to the
        // search that superseded it.
        if ( controller === request ) {
            searching.value = false;
            controller = null;
        }
    }
}

/**
 * Resolve the picked suggestion into a postcode and hand it back.
 *
 * @param {object} suggestion Suggestion row.
 * @return {Promise<void>}
 */
async function pick( suggestion ) {
    error.value = '';

    // A provider that already knew the postcode skips the resolve round-trip.
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
        :description="__( 'Type your address and pick the right option.' )"
        @close="emit( 'close' )"
    >
        <form @submit.prevent="runSearch">
            <input
                v-model="query"
                type="text"
                class="hubgo-calc__finder-field"
                autocomplete="street-address"
                :placeholder="__( 'Street, number, city…' )"
                :aria-label="__( 'Address' )"
            >
        </form>

        <Transition name="hubgo-calc-fade">
            <p v-if="error" class="hubgo-calc__error">{{ error }}</p>
        </Transition>

        <Transition name="hubgo-calc-fade">
            <div v-if="searching" class="hubgo-calc__loading hubgo-calc__finder-loading">
                <span class="hubgo-calc__spinner" aria-hidden="true" />
                {{ __( 'Searching addresses…' ) }}
            </div>
        </Transition>

        <!--
            The result panel is faded as one block rather than row by row: it is
            rebuilt on every debounced keystroke, and animating each suggestion
            would turn a steady list into a flicker.
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
