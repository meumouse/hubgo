<script setup>
/**
 * ColorField.vue
 *
 * Colour control pairing a square swatch picker with a hex input. The swatch is
 * one control unit tall and wide (3rem), so it lines up with every other field
 * on the row. The hex input is only committed once it holds a valid #RGB/#RRGGBB
 * value, so typing does not emit garbage into the settings model.
 *
 * @since 3.0.0
 * @version 3.0.1
 */
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '#008aff' },
    field: { type: Object, required: true },
    name: { type: String, required: true },
});

const emit = defineEmits([ 'update:modelValue' ]);

const HEX_PATTERN = /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i;

const draft = ref( props.modelValue || '' );

watch( () => props.modelValue, ( value ) => {
    draft.value = value || '';
} );

// The native <input type="color"> only accepts #RRGGBB, so fall back to the
// field default while the model holds a partial or empty value.
const swatchValue = computed( () => {
    const value = String( props.modelValue || '' ).trim();

    return /^#[0-9a-f]{6}$/i.test( value ) ? value : ( props.field.default || '#008aff' );
} );

// An empty model means "keep the built-in value": the swatch then paints the
// fallback muted so it does not read as a deliberate choice.
const isUnset = computed( () => '' === String( props.modelValue || '' ).trim() );

/**
 * Commit the hex input when it parses as a valid colour.
 *
 * @param {string} value Raw input value.
 * @return {void}
 */
function commitHex( value ) {
    const next = value.startsWith( '#' ) ? value : `#${ value }`;

    draft.value = value;

    if ( '' === value.trim() ) {
        emit( 'update:modelValue', '' );

        return;
    }

    if ( HEX_PATTERN.test( next ) ) {
        emit( 'update:modelValue', next.toLowerCase() );
    }
}
</script>

<template>
    <div class="flex items-center gap-3">
        <label
            class="hubgo-control relative inline-flex h-12 w-12 shrink-0 cursor-pointer items-center justify-center overflow-hidden p-0"
            :class="isUnset ? 'opacity-60' : ''"
        >
            <span class="absolute inset-0" :style="{ backgroundColor: swatchValue }" aria-hidden="true" />
            <input
                :name="name"
                :value="swatchValue"
                type="color"
                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                :aria-label="field.label || name"
                @input="$emit( 'update:modelValue', $event.target.value )"
            >
        </label>

        <input
            :value="draft"
            type="text"
            spellcheck="false"
            :placeholder="field.default || '#008aff'"
            class="w-36 font-mono text-[14px] uppercase text-slate-700"
            @input="commitHex( $event.target.value )"
        >
    </div>
</template>
