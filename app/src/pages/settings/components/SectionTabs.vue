<script setup>
defineProps({
    tabs: { type: Array, default: () => [] },
    active: { type: String, default: '' },
});

defineEmits([ 'select' ]);
</script>

<template>
    <nav class="flex flex-wrap gap-1 border-b border-slate-200">
        <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            @click="$emit( 'select', tab.id )"
            :class="[
                'flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors',
                active === tab.id
                    ? 'border-primary text-primary'
                    : 'border-transparent text-slate-500 hover:text-slate-800',
            ]"
        >
            <span v-if="tab.icon" v-html="tab.icon" class="inline-flex h-4 w-4 items-center [&>svg]:h-4 [&>svg]:w-4 [&>svg]:fill-current" />
            {{ tab.label }}
        </button>
    </nav>
</template>
