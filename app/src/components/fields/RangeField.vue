<script setup>
/**
 * RangeField.vue — slider for the calculator style values.
 *
 * A native range input cannot express "unset", but an empty value is exactly
 * what the calculator style keys use to mean "keep the built-in default". The
 * slider therefore renders at the field's `default` while the model is empty,
 * and a reset button puts it back to empty once the user has touched it.
 *
 * @since 3.1.0
 */
import { computed } from 'vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
    modelValue: { type: [ String, Number ], default: '' },
    field: { type: Object, required: true },
    name: { type: String, required: true },
    inputId: { type: String, default: '' },
});

const emit = defineEmits([ 'update:modelValue' ]);

const min = computed( () => Number( props.field.min ?? 0 ) );
const max = computed( () => Number( props.field.max ?? 40 ) );
const step = computed( () => Number( props.field.step ?? 1 ) );

const isUnset = computed( () => props.modelValue === '' || props.modelValue === null || props.modelValue === undefined );

// While unset, park the thumb on the declared default (or the low bound) so the
// control still communicates where the value currently sits.
const sliderValue = computed( () => {
    if ( ! isUnset.value ) {
        return Number( props.modelValue );
    }

    const fallback = Number( props.field.default );

    return Number.isFinite( fallback ) ? fallback : min.value;
} );
</script>

<template>
    <div class="flex items-center gap-3">
        <input
            :id="inputId || undefined"
            :name="name"
            :value="sliderValue"
            :min="min"
            :max="max"
            :step="step"
            type="range"
            class="h-2 w-40 cursor-pointer appearance-none rounded-full bg-slate-200 accent-primary-700"
            @input="emit( 'update:modelValue', $event.target.value )"
        >

        <span class="min-w-[52px] text-[13px] tabular-nums text-slate-500">
            <template v-if="isUnset">{{ __( 'padrão' ) }}</template>
            <template v-else>{{ modelValue }}{{ field.unit || '' }}</template>
        </span>

        <button
            v-if="! isUnset"
            type="button"
            class="text-[12px] text-slate-400 underline-offset-2 transition hover:text-primary-700 hover:underline"
            @click="emit( 'update:modelValue', '' )"
        >
            {{ __( 'Redefinir' ) }}
        </button>
    </div>
</template>
