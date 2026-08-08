<script setup>
/**
 * TextField.vue — single-line text control for schema-driven settings.
 *
 * The chrome (height, border, radius, focus) comes from the shared control
 * styles in main.css, so the field cannot drift from its neighbours.
 *
 * @since 3.0.0
 * @version 3.0.1
 */
defineProps({
    modelValue: { type: [ String, Number ], default: '' },
    field: { type: Object, required: true },
    name: { type: String, required: true },
    // Bound on the input (not the wrapper) so an external <label for> works.
    inputId: { type: String, default: '' },
});

defineEmits([ 'update:modelValue' ]);
</script>

<template>
    <div>
        <input
            :id="inputId || undefined"
            :name="name"
            :value="modelValue"
            :placeholder="field.placeholder || ''"
            :type="field.input_type || 'text'"
            class="w-full text-[14px] text-slate-700 md:min-w-[330px]"
            @input="$emit( 'update:modelValue', $event.target.value )"
        >
    </div>
</template>
