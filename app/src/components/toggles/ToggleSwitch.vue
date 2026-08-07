<script setup>
/**
 * ToggleSwitch.vue
 *
 * Accessible switch backed by a visually hidden checkbox. Works with boolean or
 * string models (the settings schema stores 'yes'/'no') through trueValue and
 * falseValue.
 *
 * @since 3.0.0
 */
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [ Boolean, String, Number ], default: false },
    trueValue: { type: [ Boolean, String, Number ], default: true },
    falseValue: { type: [ Boolean, String, Number ], default: false },
    label: { type: String, default: '' },
    ariaLabel: { type: String, default: '' },
    id: { type: String, default: '' },
    name: { type: String, default: '' },
    size: { type: String, default: 'sm' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits([ 'update:modelValue', 'change' ]);

const inputId = computed( () => props.id || `hubgo-toggle-${ Math.random().toString( 36 ).slice( 2, 10 ) }` );
const checked = computed( () => props.modelValue === props.trueValue );

/**
 * Emit the mapped value for the new checked state.
 *
 * @param {Event} event Native change event.
 * @return {void}
 */
function handleChange( event ) {
    const nextValue = event.target.checked ? props.trueValue : props.falseValue;

    emit( 'update:modelValue', nextValue );
    emit( 'change', nextValue );
}
</script>

<template>
    <div class="inline-flex items-center gap-3">
        <span v-if="label" class="text-[14px] font-medium text-slate-700">{{ label }}</span>

        <label
            :for="inputId"
            class="relative inline-flex cursor-pointer items-center"
            :class="{ 'cursor-not-allowed opacity-60': disabled }"
        >
            <input
                :id="inputId"
                :name="name"
                :checked="checked"
                :aria-label="ariaLabel || label || name"
                :disabled="disabled"
                type="checkbox"
                class="peer sr-only"
                @change="handleChange"
            >

            <span
                aria-hidden="true"
                :class="[
                    'inline-flex shrink-0 rounded-full border border-slate-200 bg-slate-300 transition-colors duration-200 ease-in-out',
                    size === 'md' ? 'h-6 w-11' : 'h-5 w-9',
                    'peer-focus-visible:outline-none peer-focus-visible:ring-4 peer-focus-visible:ring-primary-100',
                    'peer-checked:border-primary-700 peer-checked:bg-primary-700',
                ]"
            />

            <span
                aria-hidden="true"
                :class="[
                    'pointer-events-none absolute left-0.5 top-0.5 rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out',
                    size === 'md' ? 'h-5 w-5 peer-checked:translate-x-5' : 'h-4 w-4 peer-checked:translate-x-4',
                ]"
            />
        </label>
    </div>
</template>
