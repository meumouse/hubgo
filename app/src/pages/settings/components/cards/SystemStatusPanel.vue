<script setup>
/**
 * SystemStatusPanel.vue — environment snapshot rendered by the "About" tab.
 *
 * Purely presentational: every row (label, value and tone) is computed by
 * MeuMouse\Hubgo\Admin\System_Status and shipped in the settings bootstrap.
 *
 * @since 3.0.0
 */
import { __ } from '../../../../utils/i18n';
import StatusBadge from '../../../../components/cards/StatusBadge.vue';

defineProps({
    rows: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="py-6">
        <dl v-if="rows.length" class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
            <div
                v-for="row in rows"
                :key="row.id"
                class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3"
            >
                <dt class="text-[13px] font-medium text-slate-600">{{ row.label }}</dt>
                <dd class="m-0">
                    <StatusBadge v-if="row.tone && row.tone !== 'neutral'" :label="row.value" :tone="row.tone" />
                    <span v-else class="text-[13px] font-semibold text-slate-800">{{ row.value }}</span>
                </dd>
            </div>
        </dl>

        <p v-else class="m-0 text-[13px] text-slate-500">
            {{ __( 'Could not read the environment information.' ) }}
        </p>
    </div>
</template>
