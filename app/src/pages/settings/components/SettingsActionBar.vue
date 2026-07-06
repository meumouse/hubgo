<script setup>
import { __ } from '../../../utils/i18n';

defineProps({
    saving: { type: Boolean, default: false },
    hasUnsavedChanges: { type: Boolean, default: false },
});

defineEmits([ 'save' ]);
</script>

<template>
    <div class="sticky bottom-0 z-[1059] mt-6 flex items-center justify-end gap-3 border-t border-slate-200 bg-white/80 px-1 py-4 backdrop-blur">
        <span v-if="hasUnsavedChanges" class="text-xs text-amber-600">
            {{ __( 'Existem alterações não salvas.' ) }}
        </span>
        <button
            type="button"
            :disabled="! hasUnsavedChanges || saving"
            @click="$emit( 'save' )"
            :class="[
                'inline-flex items-center gap-2 rounded-md px-5 py-2.5 text-sm font-semibold text-white transition-colors',
                ( ! hasUnsavedChanges || saving ) ? 'cursor-not-allowed bg-slate-300' : 'bg-primary hover:bg-primary-600',
            ]"
        >
            <svg v-if="saving" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" />
            </svg>
            {{ saving ? __( 'Salvando...' ) : __( 'Salvar configurações' ) }}
        </button>
    </div>
</template>
