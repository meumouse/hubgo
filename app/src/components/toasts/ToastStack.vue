<script setup>
/**
 * ToastStack.vue — transient notifications with a tone-coloured header and an
 * auto-dismiss progress bar.
 *
 * Entering, leaving and re-stacking are handled by the TransitionGroup, backed
 * by the `hubgo-toast-*` rules in main.css. Nothing here may carry a Tailwind
 * `transition`/`opacity`/`translate` utility: they compile with `!important`
 * under the app scope and would win against the transition classes.
 *
 * @since 3.0.0
 * @version 3.1.0
 */
import { AlertTriangle, CheckCircle, InfoCircle, X, XCircle } from '@boxicons/vue';
import { TOAST_DURATION } from '../../composables/useToasts';
import { __ } from '../../utils/i18n';

defineProps({
    toasts: { type: Array, default: () => [] },
});

defineEmits([ 'dismiss' ]);

const ICONS = {
    success: CheckCircle,
    warning: AlertTriangle,
    error: XCircle,
    info: InfoCircle,
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
            <!--
                `relative` on the stack, not on the toast: a leaving toast is
                taken out of flow with `position: absolute` so the ones below it
                can slide up, and it needs this element as its containing block
                to keep its width. A `relative` on the toast itself would beat
                that rule outright — utilities carry `!important` here.
            -->
            <TransitionGroup name="hubgo-toast" tag="div" class="relative flex flex-col gap-3">
                <article
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto overflow-hidden rounded-lg border border-transparent bg-white shadow-[0_0.275rem_1.25rem_rgba(11,15,25,0.08),0_0.25rem_0.5625rem_rgba(11,15,25,0.04)]"
                >
                    <header class="flex items-center border-0 px-4 py-2 font-bold" :class="headerClass( toast.tone )">
                        <component
                            :is="iconFor( toast.tone )"
                            class="me-2 shrink-0 text-current"
                            width="20"
                            height="20"
                            aria-hidden="true"
                        />
                        <span class="me-auto min-w-0 truncate">{{ toast.title }}</span>

                        <button
                            type="button"
                            class="ms-2 flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded border-0 bg-transparent p-0 text-current opacity-70 transition hover:opacity-100"
                            :aria-label="__( 'Close' )"
                            @click="$emit( 'dismiss', toast.id )"
                        >
                            <X class="h-4 w-4" width="16" height="16" aria-hidden="true" />
                        </button>
                    </header>

                    <div class="px-4 py-4 text-[15px] leading-6 text-slate-600">
                        {{ toast.message }}
                    </div>

                    <div
                        class="hubgo-toast__progress h-[3px] w-full origin-left"
                        :class="progressClass( toast.tone )"
                        :style="{ '--hubgo-toast-duration': `${ TOAST_DURATION }ms` }"
                    />
                </article>
            </TransitionGroup>
        </div>
        </div>
    </Teleport>
</template>
