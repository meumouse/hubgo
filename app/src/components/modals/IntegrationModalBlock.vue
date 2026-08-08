<script setup>
/**
 * IntegrationModalBlock.vue — renders one content block of an integration modal.
 *
 * Blocks let an integration prepend explanatory content above its fields
 * without shipping a Vue component: the backend declares `html` or `notice`
 * blocks and this component paints them.
 *
 * @since 3.0.0
 */
import { computed } from 'vue';

const props = defineProps({
    block: { type: Object, required: true },
});

const toneClass = computed( () => ( {
    info: 'border-primary-100 bg-primary-50 text-primary-800',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    danger: 'border-rose-200 bg-rose-50 text-rose-800',
}[ props.block.tone ] || 'border-primary-100 bg-primary-50 text-primary-800' ) );
</script>

<template>
    <div
        v-if="'notice' === block.type"
        class="rounded-[8px] border px-4 py-3 text-[13px] leading-5"
        :class="toneClass"
    >
        {{ block.message }}
    </div>

    <div
        v-else-if="'html' === block.type && block.html"
        class="text-[13px] leading-6 text-slate-600"
        v-html="block.html"
    />
</template>
