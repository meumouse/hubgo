<script setup>
/**
 * SettingsActionBar.vue — sticky footer holding the save action.
 *
 * @since 3.0.0
 */
import { __ } from '../../../utils/i18n';
import BaseButton from '../../../components/buttons/BaseButton.vue';

defineProps({
    hasUnsavedChanges: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
});

defineEmits([ 'save' ]);

const SAVE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20" aria-hidden="true"><path d="M5 21h14a2 2 0 0 0 2-2V8l-5-5H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2zM7 5h6v4H7V5zm0 8h10v6H7v-6z"></path></svg>';
</script>

<template>
    <div class="sticky inset-x-0 bottom-0 z-[1059] flex flex-wrap items-center gap-4 border-t border-black/10 bg-white/80 px-10 py-6 backdrop-blur-[5px]">
        <BaseButton
            :title="__( 'Salvar alterações' )"
            :icon="SAVE_ICON"
            icon-class="text-white"
            color="primary"
            size="lg"
            :loading="saving"
            :disabled="! hasUnsavedChanges"
            @click="$emit( 'save' )"
        />

        <span v-if="hasUnsavedChanges && ! saving" class="text-[13px] font-medium text-amber-600">
            {{ __( 'Existem alterações não salvas.' ) }}
        </span>
    </div>
</template>
