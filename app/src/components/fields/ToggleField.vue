<script setup>
const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [ String, Boolean ], default: 'no' },
});

const emit = defineEmits([ 'update:modelValue' ]);

const isOn = () => props.modelValue === 'yes' || props.modelValue === true;

function toggle() {
    emit( 'update:modelValue', isOn() ? 'no' : 'yes' );
}
</script>

<template>
    <div class="flex items-start justify-between gap-4 py-3">
        <div class="min-w-0">
            <label class="block text-sm font-semibold text-slate-800">{{ field.label }}</label>
            <p v-if="field.description" class="mt-1 text-xs text-slate-500">{{ field.description }}</p>
        </div>

        <button
            type="button"
            role="switch"
            :aria-checked="isOn()"
            @click="toggle"
            :class="[
                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors',
                isOn() ? 'bg-primary' : 'bg-slate-300',
            ]"
        >
            <span
                :class="[
                    'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform',
                    isOn() ? 'translate-x-5' : 'translate-x-0',
                ]"
            />
        </button>
    </div>
</template>
