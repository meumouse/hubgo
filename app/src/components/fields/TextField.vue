<script setup>
/**
 * TextField.vue — single-line text control for schema-driven settings.
 *
 * @since 3.0.0
 * @version 3.0.0
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
            class="w-full rounded-[8px] border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100 md:min-w-[330px]"
            @input="$emit( 'update:modelValue', $event.target.value )"
        >
    </div>
</template>
