<script setup>
/**
 * DimensionField.vue — CSS length control (amount + unit).
 *
 * Renders one control: a numeric input paired with a compact unit picker. The
 * model is the CSS value itself ("12px", "1.5rem", "50%"), which is what the
 * calculator's custom properties consume, so nothing downstream has to
 * re-assemble the two halves.
 *
 * An empty amount emits an empty string — the calculator style keys read "" as
 * "keep the built-in value", so a cleared field must not become a zero. A value
 * stored before the unit picker existed is a bare number; it is read with the
 * field's declared unit and re-saved with the unit spelled out.
 *
 * @since 3.0.1
 */
import { computed, ref, watch } from 'vue';
import UnitSelect from './UnitSelect.vue';

const props = defineProps({
    modelValue: { type: [ String, Number ], default: '' },
    field: { type: Object, required: true },
    name: { type: String, required: true },
    inputId: { type: String, default: '' },
});

const emit = defineEmits([ 'update:modelValue' ]);

const DEFAULT_UNITS = [ 'rem', 'em', 'px', '%' ];

const VALUE_PATTERN = /^(-?\d*\.?\d+)\s*(rem|em|px|%)?$/i;

// Fractional units deserve a finer keyboard step than whole pixels do.
const FRACTIONAL_STEP = { rem: 0.1, em: 0.1 };

const units = computed( () => {
    const declared = Array.isArray( props.field.units ) ? props.field.units.filter( Boolean ) : [];

    return declared.length ? declared : DEFAULT_UNITS;
} );

const fallbackUnit = computed( () => {
    const declared = String( props.field.unit || '' ).toLowerCase();

    return units.value.includes( declared ) ? declared : units.value[ 0 ];
} );

/**
 * Split a stored value into its amount and its unit.
 *
 * @param {string|number} value Stored setting value.
 * @return {{ amount: string, unit: string }}
 */
function parse( value ) {
    const raw = String( value ?? '' ).trim().toLowerCase();
    const matches = raw ? raw.match( VALUE_PATTERN ) : null;

    if ( ! matches ) {
        return { amount: '', unit: fallbackUnit.value };
    }

    const unit = matches[ 2 ] || fallbackUnit.value;

    return {
        amount: matches[ 1 ],
        unit: units.value.includes( unit ) ? unit : fallbackUnit.value,
    };
}

const amount = ref( parse( props.modelValue ).amount );
const unit = ref( parse( props.modelValue ).unit );
const menuOpen = ref( false );

watch( () => props.modelValue, ( value ) => {
    const parsed = parse( value );

    amount.value = parsed.amount;
    unit.value = parsed.unit;
} );

const step = computed( () => {
    if ( props.field.step ) {
        return props.field.step;
    }

    return FRACTIONAL_STEP[ unit.value ] || 1;
} );

// Show the built-in default as the placeholder so an empty field reads as
// "unchanged" rather than "zero".
const placeholder = computed( () => {
    if ( props.field.placeholder ) {
        return String( props.field.placeholder );
    }

    return '' !== String( props.field.default ?? '' ) ? String( props.field.default ) : '';
} );

/**
 * Emit the assembled CSS value, or an empty string when the amount is cleared.
 *
 * @return {void}
 */
function commit() {
    const next = String( amount.value ).trim();

    emit( 'update:modelValue', '' === next ? '' : `${ next }${ unit.value }` );
}

/**
 * Store the typed amount and commit it.
 *
 * @param {string} value Raw input value.
 * @return {void}
 */
function commitAmount( value ) {
    amount.value = value;

    commit();
}

/**
 * Store the picked unit and re-commit the current amount with it.
 *
 * @param {string} value Selected unit.
 * @return {void}
 */
function commitUnit( value ) {
    unit.value = value;

    commit();
}
</script>

<template>
    <div class="hubgo-control inline-flex w-44 items-stretch overflow-hidden bg-white" :class="menuOpen ? 'is-focused' : ''">
        <input
            :id="inputId || undefined"
            :name="name"
            :value="amount"
            :placeholder="placeholder"
            :min="field.min"
            :max="field.max"
            :step="step"
            type="number"
            inputmode="decimal"
            class="hubgo-control__inner w-full min-w-0 flex-1 px-3.5 text-[14px] text-slate-700"
            @input="commitAmount( $event.target.value )"
        >

        <UnitSelect
            :model-value="unit"
            :units="units"
            :aria-label="field.label || name"
            @update:model-value="commitUnit"
            @open="menuOpen = true"
            @close="menuOpen = false"
        />
    </div>
</template>
