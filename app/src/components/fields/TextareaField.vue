<script setup>
/**
 * TextareaField.vue — multi-line text control for schema-driven settings.
 *
 * @since 3.0.0
 */
defineProps({
    modelValue: { type: [ String, Number ], default: '' },
    field: { type: Object, required: true },
    name: { type: String, required: true },
});

defineEmits([ 'update:modelValue' ]);
</script>

<template>
    <textarea
        :name="name"
        :rows="field.rows || 4"
        :placeholder="field.placeholder || ''"
        :value="modelValue"
        class="w-full resize-y rounded-[8px] border border-slate-200 bg-white px-4 py-3 text-[14px] leading-6 text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100 md:min-w-[330px]"
        @input="$emit( 'update:modelValue', $event.target.value )"
    />
</template>
