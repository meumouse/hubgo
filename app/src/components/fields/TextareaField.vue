<script setup>
defineProps({
    field: { type: Object, required: true },
    modelValue: { type: String, default: '' },
});

defineEmits([ 'update:modelValue' ]);
</script>

<template>
    <div class="py-3">
        <label class="block text-sm font-semibold text-slate-800">{{ field.label }}</label>
        <p v-if="field.description" class="mt-1 mb-2 text-xs text-slate-500">{{ field.description }}</p>
        <textarea
            :value="modelValue"
            :rows="field.rows || 3"
            :placeholder="field.placeholder || ''"
            @input="$emit( 'update:modelValue', $event.target.value )"
            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
        />
    </div>
</template>
