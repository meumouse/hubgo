<script setup>
/**
 * TextareaField.vue — multi-line text control for schema-driven settings.
 *
 * Chrome and focus come from the shared control styles in main.css.
 *
 * @since 3.0.0
 * @version 3.0.1
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
        class="w-full resize-y text-[14px] leading-6 text-slate-700 md:min-w-[330px]"
        @input="$emit( 'update:modelValue', $event.target.value )"
    />
</template>
