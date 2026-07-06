<script setup>
import { computed } from 'vue';
import { resolveFieldComponent } from './fieldRegistry';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [ String, Boolean, Number ], default: '' },
});

defineEmits([ 'update:modelValue' ]);

const component = computed( () => resolveFieldComponent( props.field ) );
</script>

<template>
    <component
        :is="component"
        v-if="component"
        :field="field"
        :model-value="modelValue"
        @update:model-value="$emit( 'update:modelValue', $event )"
    />
    <div v-else class="py-3 text-xs text-rose-500">
        Unsupported field type: {{ field.type || field.component }}
    </div>
</template>
