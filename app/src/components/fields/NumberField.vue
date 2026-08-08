<script setup>
/**
 * NumberField.vue — numeric control for schema-driven settings.
 *
 * Emits an empty string when the input is cleared instead of coercing to zero:
 * several calculator settings read "" as "use the built-in default", and a
 * silent 0 would mean something very different.
 *
 * @since 3.0.0
 */
defineProps({
    modelValue: { type: [ String, Number ], default: '' },
    field: { type: Object, required: true },
    name: { type: String, required: true },
    inputId: { type: String, default: '' },
});

const emit = defineEmits([ 'update:modelValue' ]);

/**
 * Forward the raw input value, preserving "empty" as an empty string.
 *
 * @param {string} value Raw input value.
 * @return {void}
 */
function commit( value ) {
    emit( 'update:modelValue', value === '' ? '' : value );
}
</script>

<template>
    <div class="flex items-center gap-2">
        <input
            :id="inputId || undefined"
            :name="name"
            :value="modelValue"
            :placeholder="field.placeholder || ''"
            :min="field.min"
            :max="field.max"
            :step="field.step || 1"
            type="number"
            inputmode="decimal"
            class="w-32 rounded-[8px] border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
            @input="commit( $event.target.value )"
        >

        <span v-if="field.unit" class="text-[13px] text-slate-400">{{ field.unit }}</span>
    </div>
</template>
