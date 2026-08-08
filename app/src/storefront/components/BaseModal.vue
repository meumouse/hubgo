<script setup>
/**
 * BaseModal.vue — dialog shell for the storefront calculator.
 *
 * Teleported to <body> on purpose: themes routinely put `overflow: hidden` or a
 * `transform` on product-page ancestors, either of which breaks `position:
 * fixed` for a dialog rendered in place.
 *
 * The cost of teleporting is that the dialog leaves the element the calculator's
 * custom properties are declared on, so it would fall back to the stylesheet
 * defaults and ignore both the settings panel and Elementor. `bridgeTokens()`
 * copies the resolved values across on open, which is why the modal is styled
 * identically to the card that opened it.
 *
 * @since 3.1.0
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { bridgeTokens } from '../tokens';
import { __ } from '../../utils/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    /** Widget root the custom properties are read from. */
    tokenSource: { type: [ Object, null ], default: null },
});

const emit = defineEmits([ 'close' ]);

const rootEl = ref( null );
const dialogEl = ref( null );

watch( () => props.open, async ( isOpen ) => {
    if ( ! isOpen ) {
        document.removeEventListener( 'keydown', handleKeydown );

        return;
    }

    document.addEventListener( 'keydown', handleKeydown );

    await nextTick();

    bridgeTokens( props.tokenSource, rootEl.value );

    if ( dialogEl.value ) {
        dialogEl.value.focus();
    }
} );

onBeforeUnmount( () => document.removeEventListener( 'keydown', handleKeydown ) );

/**
 * Close on Escape.
 *
 * @param {KeyboardEvent} event Key event.
 * @return {void}
 */
function handleKeydown( event ) {
    if ( event.key === 'Escape' ) {
        emit( 'close' );
    }
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            ref="rootEl"
            class="hubgo-calc-modal"
            role="dialog"
            aria-modal="true"
        >
            <div class="hubgo-calc-modal__overlay" @click="emit( 'close' )" />

            <div ref="dialogEl" class="hubgo-calc-modal__dialog" tabindex="-1">
                <div class="hubgo-calc-modal__header">
                    <div>
                        <div class="hubgo-calc-modal__title">{{ title }}</div>
                        <div v-if="description" class="hubgo-calc-modal__description">{{ description }}</div>
                    </div>

                    <button
                        type="button"
                        class="hubgo-calc-modal__close"
                        :aria-label="__( 'Fechar' )"
                        @click="emit( 'close' )"
                    >
                        &times;
                    </button>
                </div>

                <div class="hubgo-calc-modal__body">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
