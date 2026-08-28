<script setup>
/**
 * SectionTabs.vue — segmented navigation between settings sections.
 *
 * Section icons arrive from the PHP schema as Boxicons *names* ("palette"),
 * resolved here through the shared icon registry. Raw SVG markup is still
 * accepted so a third-party section can ship its own glyph.
 *
 * @since 3.0.0
 * @version 3.0.0
 */
import { isMarkup, resolveIcon } from '../../../utils/icons';

defineProps({
    sections: { type: Array, default: () => [] },
    activeSectionId: { type: String, default: '' },
});

defineEmits([ 'select' ]);
</script>

<template>
    <nav class="mt-10 flex w-fit max-w-full flex-wrap overflow-hidden rounded-[8px] bg-[#e7edf5] p-0.5">
        <button
            v-for="section in sections"
            :key="section.id"
            type="button"
            class="hubgo-tab flex min-w-[165px] cursor-pointer items-center justify-center gap-2 rounded-none border-0 px-6 py-5 text-[15px] font-semibold uppercase tracking-wide transition first:rounded-l-[8px] last:rounded-r-[8px]"
            :class="[
                activeSectionId === section.id
                    ? 'active bg-primary-700 text-white shadow-sm'
                    : 'bg-transparent text-slate-600 hover:text-slate-800',
            ]"
            @click="$emit( 'select', section.id )"
        >
            <span
                v-if="isMarkup( section.icon )"
                class="hubgo-tab-icon inline-flex h-[22px] w-[22px] shrink-0 items-center justify-center leading-none"
                v-html="section.icon"
            />
            <component
                :is="resolveIcon( section.icon, null )"
                v-else-if="section.icon"
                class="shrink-0"
                width="22"
                height="22"
                aria-hidden="true"
            />
            <span>{{ section.title }}</span>
        </button>
    </nav>
</template>
