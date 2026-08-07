<script setup>
/**
 * FieldControl.vue
 *
 * Renders only the control for a schema field (no label/description — FieldRow
 * owns those). Resolves the component through the field registry so third
 * parties can register their own widgets.
 *
 * @since 3.0.0
 */
import { computed } from 'vue';
import ToggleSwitch from '../toggles/ToggleSwitch.vue';
import TextField from './TextField.vue';
import { resolveFieldComponent } from './fieldRegistry';

const props = defineProps({
    modelValue: { type: [ String, Number, Boolean, Object, Array ], default: '' },
    field: { type: Object, required: true },
    name: { type: String, required: true },
});

const emit = defineEmits([ 'update:modelValue' ]);

const fieldComponent = computed( () => resolveFieldComponent( props.field ) || TextField );

// A plain "toggle" type renders the switch directly so it can bind yes/no.
const usesToggle = computed(
    () => String( props.field?.type || '' ).toLowerCase() === 'toggle' && ! props.field?.component,
);

const model = computed({
    get: () => props.modelValue,
    set: ( value ) => emit( 'update:modelValue', value ),
});
</script>

<template>
    <ToggleSwitch
        v-if="usesToggle"
        :id="name"
        v-model="model"
        :name="name"
        :aria-label="field.label"
        size="md"
        true-value="yes"
        false-value="no"
    />

    <component
        :is="fieldComponent"
        v-else
        v-model="model"
        :field="field"
        :name="name"
    />
</template>
