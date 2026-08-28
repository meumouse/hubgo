<script setup>
/**
 * PasswordField.vue — masked control for credentials (API keys).
 *
 * The value is masked by default and revealed on demand. It is NOT write-only:
 * the store owner has to be able to check which key is in place, and the value
 * already travels over the same authenticated REST call as every other setting.
 *
 * @since 3.0.0
 * @version 3.0.1
 */
import { ref } from 'vue';
import { Eye, EyeSlash } from '@boxicons/vue';
import { __ } from '../../utils/i18n';

defineProps({
    modelValue: { type: String, default: '' },
    field: { type: Object, required: true },
    name: { type: String, required: true },
    inputId: { type: String, default: '' },
});

defineEmits([ 'update:modelValue' ]);

const revealed = ref( false );
</script>

<template>
    <div class="hubgo-control flex items-stretch overflow-hidden bg-white md:min-w-[330px]">
        <input
            :id="inputId || undefined"
            :name="name"
            :value="modelValue"
            :placeholder="field.placeholder || ''"
            :type="revealed ? 'text' : 'password'"
            spellcheck="false"
            autocomplete="off"
            class="hubgo-control__inner w-full min-w-0 flex-1 px-3.5 font-mono text-[13px] text-slate-700 placeholder:font-sans"
            @input="$emit( 'update:modelValue', $event.target.value )"
        >

        <button
            type="button"
            class="flex shrink-0 items-center justify-center px-3 text-slate-400 transition hover:text-primary-700"
            :aria-label="revealed ? __( 'Hide' ) : __( 'Show' )"
            :title="revealed ? __( 'Hide' ) : __( 'Show' )"
            @click="revealed = ! revealed"
        >
            <component
                :is="revealed ? EyeSlash : Eye"
                class="h-[18px] w-[18px]"
                width="18"
                height="18"
                aria-hidden="true"
            />
        </button>
    </div>
</template>
