<script setup>
/**
 * StatusBadge.vue — pill label used for statuses and counters.
 *
 * @since 3.0.0
 */
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    tone: {
        type: String,
        default: 'neutral',
        validator: ( value ) => [ 'neutral', 'success', 'warning', 'danger', 'info' ].includes( value ),
    },
});

const badgeClass = computed( () => ( {
    neutral: 'bg-slate-100 text-slate-700',
    success: 'bg-emerald-100 text-emerald-700',
    warning: 'bg-amber-100 text-amber-700',
    danger: 'bg-rose-100 text-rose-700',
    info: 'bg-primary-100 text-primary-800',
}[ props.tone ] || 'bg-slate-100 text-slate-700' ) );
</script>

<template>
    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" :class="badgeClass">
        {{ label }}
    </span>
</template>
