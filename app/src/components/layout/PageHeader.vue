<script setup>
/**
 * PageHeader.vue — standardized admin page header.
 *
 * Renders the shared "HubGo" eyebrow, brand mark, page title and an optional
 * description. Every admin page uses it so the headers stay consistent, each
 * passing its own title/description.
 *
 * Slots:
 * - description: rich description content (overrides the `description` prop),
 *   useful when the copy needs inline links.
 * - actions: right-aligned content (overrides the built-in action button).
 *
 * @since 3.0.0
 */
import { __ } from '../../utils/i18n';
import BaseButton from '../buttons/BaseButton.vue';
import BrandMark from '../brand/BrandMark.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    actionLabel: { type: String, default: '' },
    actionHref: { type: String, default: '' },
    actionDisabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});
</script>

<template>
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-shell-500">{{ __( 'HubGo' ) }}</p>

            <div class="flex items-center gap-3">
                <BrandMark size="md" variant="primary" />
                <h1 class="m-0 p-0 text-3xl font-semibold tracking-tight text-ink">{{ title }}</h1>
            </div>

            <p v-if="$slots.description || description" class="max-w-3xl text-sm leading-6 text-shell-500">
                <slot name="description">{{ description }}</slot>
            </p>
        </div>

        <slot name="actions">
            <BaseButton
                v-if="actionLabel"
                :disabled="actionDisabled"
                :href="actionHref"
                :loading="loading"
                :title="actionLabel"
                color="primary"
            />
        </slot>
    </header>
</template>
