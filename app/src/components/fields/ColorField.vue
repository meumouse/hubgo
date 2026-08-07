<script setup>
/**
 * ColorField.vue
 *
 * Colour control pairing a native swatch picker with a hex input. The hex input
 * is only committed once it holds a valid #RGB/#RRGGBB value, so typing does not
 * emit garbage into the settings model.
 *
 * @since 3.0.0
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

/**
 * Commit the hex input when it parses as a valid colour.
 *
 * @param {string} value Raw input value.
 * @return {void}
 */
function commitHex( value ) {
    const next = value.startsWith( '#' ) ? value : `#${ value }`;

    draft.value = value;

    if ( HEX_PATTERN.test( next ) ) {
        emit( 'update:modelValue', next.toLowerCase() );
    }
}
</script>

<template>
    <div class="flex items-center gap-3">
        <label class="relative inline-flex h-11 w-14 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-[8px] border border-slate-200 shadow-sm">
            <span class="absolute inset-0" :style="{ backgroundColor: swatchValue }" aria-hidden="true" />
            <input
                :name="name"
                :value="swatchValue"
                type="color"
                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                @input="$emit( 'update:modelValue', $event.target.value )"
            >
        </label>

        <input
            :value="draft"
            type="text"
            spellcheck="false"
            placeholder="#008aff"
            class="w-36 rounded-[8px] border border-slate-200 bg-white px-4 py-3 font-mono text-[14px] uppercase text-slate-700 outline-none transition focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
            @input="commitHex( $event.target.value )"
        >
    </div>
</template>
