<script setup>
/**
 * SectionCard.vue — titled panel used to group related settings or content.
 *
 * @since 3.0.0
 */
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
});
</script>

<template>
    <section class="rounded-lg border border-slate-200 bg-white/90 p-5 shadow-soft backdrop-blur">
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <p v-if="eyebrow" class="m-0 text-xs font-semibold uppercase tracking-[0.2em] text-shell-500">
                    {{ eyebrow }}
                </p>
                <h2 class="mb-0 mt-1 text-xl font-semibold text-ink">{{ title }}</h2>
                <p v-if="description" class="mb-0 mt-2 max-w-3xl text-sm leading-6 text-muted">
                    {{ description }}
                </p>
            </div>

            <div v-if="$slots.actions" class="shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <div>
            <slot />
        </div>
    </section>
</template>
