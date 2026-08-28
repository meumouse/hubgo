<script setup>
/**
 * NumberField.vue — numeric control for schema-driven settings.
 *
 * Emits an empty string when the input is cleared instead of coercing to zero:
 * several calculator settings read "" as "use the built-in default", and a
 * silent 0 would mean something very different.
 *
 * A declared `unit` renders as a suffix inside the control shell, so the number
 * and its unit read as one control instead of two loose elements on the row.
 *
 * @since 3.0.0
 * @version 3.0.1
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
    <div class="hubgo-control inline-flex w-40 items-stretch overflow-hidden bg-white">
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
            class="hubgo-control__inner w-full min-w-0 flex-1 px-3.5 text-[14px] text-slate-700"
            @input="commit( $event.target.value )"
        >

        <span
            v-if="field.unit"
            class="flex shrink-0 items-center border-l border-slate-100 px-3 text-[13px] font-medium text-slate-400"
        >
            {{ field.unit }}
        </span>
    </div>
</template>
