<script setup>
/**
 * ModalDialog.vue — base modal shell (backdrop, header, scrollable body).
 *
 * Teleported to <body> so wp-admin stacking contexts cannot clip it.
 *
 * @since 3.0.0
 * @version 3.1.0
 */
import { onBeforeUnmount, watch } from 'vue';
import { X } from '@boxicons/vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
    sizeClass: { type: String, default: 'max-w-2xl' },
});

const emit = defineEmits([ 'close' ]);

// Restored verbatim on release rather than reset to a fixed value: the styles
// belong to wp-admin, not to us, and an empty string is not the same as "what
// it was before we touched it".
let previousOverflow = '';
let previousPaddingRight = '';
let isLocked = false;

/**
 * Close the dialog when Escape is pressed.
 *
 * @param {KeyboardEvent} event Native keydown event.
 * @return {void}
 */
function onKeydown( event ) {
    if ( 'Escape' === event.key ) {
        emit( 'close' );
    }
}

/**
 * Stop the page behind the dialog from scrolling.
 *
 * The dialog owns a scroll container of its own, so without this the wheel
 * reaches wp-admin as soon as the pointer leaves the panel and two surfaces
 * scroll at once. Hiding the body overflow reclaims the scrollbar's width,
 * which would shift the whole admin screen sideways — the padding gives that
 * width back.
 *
 * @return {void}
 */
function lockScroll() {
    if ( isLocked || typeof document === 'undefined' ) {
        return;
    }

    const gutter = window.innerWidth - document.documentElement.clientWidth;

    previousOverflow = document.body.style.overflow;
    previousPaddingRight = document.body.style.paddingRight;

    document.body.style.overflow = 'hidden';

    if ( gutter > 0 ) {
        document.body.style.paddingRight = `${ gutter }px`;
    }

    isLocked = true;
}

/**
 * Give the page its scrolling back.
 *
 * @return {void}
 */
function unlockScroll() {
    if ( ! isLocked ) {
        return;
    }

    document.body.style.overflow = previousOverflow;
    document.body.style.paddingRight = previousPaddingRight;
    isLocked = false;
}

watch( () => props.open, ( isOpen ) => {
    if ( isOpen ) {
        document.addEventListener( 'keydown', onKeydown );
        lockScroll();
    } else {
        document.removeEventListener( 'keydown', onKeydown );
        unlockScroll();
    }
}, { immediate: true } );

onBeforeUnmount( () => {
    document.removeEventListener( 'keydown', onKeydown );
    unlockScroll();
} );
</script>

<template>
    <!--
        The wrapper — not the overlay — carries `hubgo-app`. Tailwind's scoping
        (`important: '.hubgo-app'`) compiles utilities to DESCENDANT selectors, so
        they never apply to the element holding the class itself. `display: contents`
        keeps the wrapper out of the layout.
    -->
    <Teleport to="body">
        <div class="hubgo-app" style="display: contents">
        <!--
            The transition is named and backed by plain CSS in main.css, never by
            Tailwind utilities bound to the enter/leave props: those sets share
            the same tokens, and Vue's class bookkeeping then leaves both the
            "from" and the "to" utility on the node — the leave never resolves
            and the dialog stays in the DOM. The select dropdown was bitten by
            exactly that.
        -->
        <Transition name="hubgo-modal">
        <div
            v-if="open"
            class="hubgo-modal fixed inset-0 z-[160000] flex items-center justify-center overflow-y-auto px-4 py-4 sm:py-6"
        >
            <button
                type="button"
                class="absolute inset-0 cursor-default border-0 bg-slate-950/60 p-0 backdrop-blur-sm"
                :aria-label="__( 'Close' )"
                @click="$emit( 'close' )"
            />

            <div
                class="hubgo-modal__panel relative z-10 max-h-[calc(100dvh-4rem)] w-full overflow-y-auto rounded-lg border border-white/20 bg-white p-6 shadow-soft sm:max-h-[calc(100dvh-5rem)]"
                :class="sizeClass"
                role="dialog"
                aria-modal="true"
            >
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <p v-if="eyebrow" class="m-0 text-xs font-semibold uppercase tracking-[0.2em] text-shell-500">
                            {{ eyebrow }}
                        </p>
                        <h3 class="mb-0 mt-1 text-xl font-semibold text-ink">{{ title }}</h3>
                        <p v-if="description" class="mb-0 mt-2 text-sm leading-6 text-muted">{{ description }}</p>
                    </div>

                    <button
                        type="button"
                        class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-[8px] border-0 bg-transparent text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        :aria-label="__( 'Close' )"
                        @click="$emit( 'close' )"
                    >
                        <X class="h-5 w-5" width="20" height="20" aria-hidden="true" />
                    </button>
                </div>

                <slot />
            </div>
        </div>
        </Transition>
        </div>
    </Teleport>
</template>
