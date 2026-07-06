<script setup>
defineProps({
    field: { type: Object, required: true },
    modelValue: { type: String, default: '#008aff' },
});

defineEmits([ 'update:modelValue' ]);
</script>

<template>
    <div class="py-3">
        <label class="block text-sm font-semibold text-slate-800">{{ field.label }}</label>
        <p v-if="field.description" class="mt-1 mb-2 text-xs text-slate-500">{{ field.description }}</p>
        <div class="flex items-center gap-3">
            <input
                type="color"
                :value="modelValue"
                @input="$emit( 'update:modelValue', $event.target.value )"
                class="h-9 w-12 cursor-pointer rounded border border-slate-300"
            />
            <input
                type="text"
                :value="modelValue"
                @input="$emit( 'update:modelValue', $event.target.value )"
                class="w-32 rounded-md border border-slate-300 px-3 py-2 text-sm uppercase shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            />
        </div>
    </div>
</template>
