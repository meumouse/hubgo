<script setup>
/**
 * ToastStack.vue — transient notifications with a tone-coloured header and an
 * auto-dismiss progress bar.
 *
 * @since 3.0.0
 */
import { __ } from '../../utils/i18n';

defineProps({
    toasts: { type: Array, default: () => [] },
});

defineEmits([ 'dismiss' ]);

const ICONS = {
    success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2 1.75 20h20.5L12 2zm0 4.65 6.06 10.65H5.94L12 6.65z" fill="currentColor"/><path d="M11 9h2v5h-2zm0 6h2v2h-2z" fill="currentColor"/></svg>',
    error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M11 11h2v6h-2zm0-4h2v2h-2z" fill="currentColor"/></svg>',
    info: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M11 10h2v7h-2zm0-4h2v2h-2z" fill="currentColor"/></svg>',
};

const HEADER_CLASSES = {
    success: 'bg-success text-white',
    warning: 'bg-warning text-dark',
    error: 'bg-danger text-white',
    info: 'bg-info text-white',
};

const PROGRESS_CLASSES = {
    success: 'bg-success',
    warning: 'bg-warning',
    error: 'bg-danger',
    info: 'bg-info',
};

function iconFor( tone ) {
    return ICONS[ tone ] || ICONS.info;
}

function headerClass( tone ) {
    return HEADER_CLASSES[ tone ] || HEADER_CLASSES.info;
}

function progressClass( tone ) {
    return PROGRESS_CLASSES[ tone ] || PROGRESS_CLASSES.info;
}
</script>

<template>
    <!--
        The wrapper — not the stack — carries `hubgo-app`. Tailwind's scoping
        (`important: '.hubgo-app'`) compiles utilities to DESCENDANT selectors, so
        they never apply to the element holding the class itself. `display: contents`
        keeps the wrapper out of the layout.
    -->
    <Teleport to="body">
        <div class="hubgo-app" style="display: contents">
        <div
            class="hubgo-toasts pointer-events-none fixed right-4 top-12 z-[160001] w-[350px] max-w-full"
            aria-live="polite"
            aria-atomic="true"
        >
            <TransitionGroup name="hubgo-toast" tag="div" class="flex flex-col gap-3">
                <article
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto relative overflow-hidden rounded-lg border border-transparent bg-white shadow-[0_0.275rem_1.25rem_rgba(11,15,25,0.08),0_0.25rem_0.5625rem_rgba(11,15,25,0.04)] transition-all duration-200 ease-out"
                    :class="toast.closing ? 'translate-y-1 opacity-0' : 'translate-y-0 opacity-100'"
                >
                    <header class="flex items-center border-0 px-4 py-2 font-bold" :class="headerClass( toast.tone )">
                        <span class="me-2 inline-flex h-5 w-5 shrink-0 text-current" v-html="iconFor( toast.tone )" />
                        <span class="me-auto min-w-0 truncate">{{ toast.title }}</span>

                        <button
                            type="button"
                            class="ms-2 flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded border-0 bg-transparent p-0 text-current opacity-70 transition hover:opacity-100"
                            :aria-label="__( 'Fechar' )"
                            @click="$emit( 'dismiss', toast.id )"
                        >
                            <svg class="h-3 w-3" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <path d="M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 1 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 1 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z" />
                            </svg>
                        </button>
                    </header>

                    <div class="px-4 py-4 text-[15px] leading-6 text-slate-600">
                        {{ toast.message }}
                    </div>

                    <div
                        class="h-[3px] w-full origin-left"
                        :class="progressClass( toast.tone )"
                        style="animation: hubgo-toast-progress 3s linear forwards"
                    />
                </article>
            </TransitionGroup>
        </div>
        </div>
    </Teleport>
</template>
