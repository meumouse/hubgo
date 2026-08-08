<script setup>
/**
 * PasswordField.vue — masked control for credentials (API keys).
 *
 * The value is masked by default and revealed on demand. It is NOT write-only:
 * the store owner has to be able to check which key is in place, and the value
 * already travels over the same authenticated REST call as every other setting.
 *
 * @since 3.0.0
 */
import { ref } from 'vue';
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
    <div class="relative md:min-w-[330px]">
        <input
            :id="inputId || undefined"
            :name="name"
            :value="modelValue"
            :placeholder="field.placeholder || ''"
            :type="revealed ? 'text' : 'password'"
            spellcheck="false"
            autocomplete="off"
            class="w-full rounded-[8px] border border-slate-200 bg-white py-3 pl-4 pr-12 font-mono text-[13px] text-slate-700 outline-none transition placeholder:font-sans placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
            @input="$emit( 'update:modelValue', $event.target.value )"
        >

        <button
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-[6px] px-2 py-1 text-[12px] text-slate-400 transition hover:bg-slate-50 hover:text-slate-600"
            :aria-label="revealed ? __( 'Ocultar' ) : __( 'Mostrar' )"
            @click="revealed = ! revealed"
        >
            {{ revealed ? __( 'Ocultar' ) : __( 'Mostrar' ) }}
        </button>
    </div>
</template>
