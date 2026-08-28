<script setup>
/**
 * FieldRow.vue
 *
 * Two-column settings row: label + description on the left, the control on the
 * right. This is the canonical layout for every schema-driven setting.
 *
 * @since 3.0.0
 */
import FieldControl from './FieldControl.vue';

defineProps({
    modelValue: { type: [ String, Number, Boolean, Object, Array ], default: '' },
    field: { type: Object, required: true },
    name: { type: String, required: true },
});

defineEmits([ 'update:modelValue' ]);
</script>

<template>
    <div class="grid items-start gap-6 py-6 lg:grid-cols-[minmax(0,420px)_minmax(0,460px)] lg:items-center">
        <div>
            <h3 class="m-0 text-[15px] font-semibold text-slate-800">{{ field.label || field.key || '' }}</h3>
            <p v-if="field.description" class="mb-0 mt-1 max-w-xl text-[13px] leading-5 text-slate-500">
                {{ field.description }}
            </p>
        </div>

        <div class="hubgo-field-control lg:justify-self-start">
            <FieldControl
                :field="field"
                :name="name"
                :model-value="modelValue"
                @update:model-value="$emit( 'update:modelValue', $event )"
            />
        </div>
    </div>
</template>
