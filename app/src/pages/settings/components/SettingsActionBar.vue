<script setup>
/**
 * SettingsActionBar.vue — sticky footer holding the save action.
 *
 * @since 3.0.0
 */
import { computed } from 'vue';
import { Save } from '@boxicons/vue';
import { __ } from '../../../utils/i18n';
import BaseButton from '../../../components/buttons/BaseButton.vue';

const props = defineProps({
    hasUnsavedChanges: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
});

defineEmits([ 'save' ]);

/** BaseButton swaps the icon for its spinner; the label carries the state too. */
const buttonTitle = computed( () => ( props.saving ? __( 'Saving…' ) : __( 'Save changes' ) ) );
</script>

<template>
    <div class="sticky inset-x-0 bottom-0 z-[1059] flex flex-wrap items-center gap-4 border-t border-black/10 bg-white/80 px-10 py-6 backdrop-blur-[5px]">
        <BaseButton
            :title="buttonTitle"
            :icon="Save"
            icon-class="text-white"
            color="primary"
            size="lg"
            :loading="saving"
            :disabled="! hasUnsavedChanges"
            @click="$emit( 'save' )"
        />

        <span v-if="hasUnsavedChanges && ! saving" class="text-[13px] font-medium text-amber-600">
            {{ __( 'There are unsaved changes.' ) }}
        </span>
    </div>
</template>
