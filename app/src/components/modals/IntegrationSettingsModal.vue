<script setup>
/**
 * IntegrationSettingsModal.vue — settings dialog for a single integration.
 *
 * Reuses the shared field system, so an integration declares its controls the
 * same way the settings schema does and gets the same widgets for free.
 *
 * @since 3.0.0
 */
import { computed } from 'vue';
import { __ } from '../../utils/i18n';
import ModalDialog from './ModalDialog.vue';
import FieldRow from '../fields/FieldRow.vue';
import IntegrationModalBlock from './IntegrationModalBlock.vue';

const MODAL_SIZE_CLASSES = {
    small: 'max-w-[640px]',
    medium: 'max-w-[900px]',
    large: 'max-w-[1200px]',
    'extra-large': 'max-w-[1400px]',
};

const props = defineProps({
    open: { type: Boolean, default: false },
    integration: { type: Object, default: null },
    settings: { type: Object, default: () => ( {} ) },
});

defineEmits([ 'close', 'update-setting', 'toast', 'refresh' ]);

const modal = computed( () => props.integration?.modal || {} );
const fields = computed( () => ( Array.isArray( props.integration?.settings ) ? props.integration.settings : [] ) );
const blocks = computed( () => ( Array.isArray( modal.value.blocks ) ? modal.value.blocks : [] ) );

const sizeClass = computed( () => MODAL_SIZE_CLASSES[ modal.value.size ] || MODAL_SIZE_CLASSES.medium );
</script>

<template>
    <ModalDialog
        :open="open"
        :title="modal.title || integration?.title || __( 'Configurações da integração' )"
        :description="modal.description || ''"
        :eyebrow="__( 'Integrações' )"
        :size-class="sizeClass"
        @close="$emit( 'close' )"
    >
        <div class="space-y-4">
            <IntegrationModalBlock
                v-for="block in blocks"
                :key="block.key"
                :block="block"
                @toast="( message, tone ) => $emit( 'toast', message, tone )"
                @refresh="$emit( 'refresh' )"
            />

            <div v-if="fields.length" class="divide-y divide-slate-100">
                <FieldRow
                    v-for="field in fields"
                    :key="field.key"
                    :field="field"
                    :name="field.key"
                    :model-value="settings[ field.key ]"
                    @update:model-value="$emit( 'update-setting', field.key, $event )"
                />
            </div>

            <p
                v-else-if="! blocks.length"
                class="m-0 rounded-[8px] border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
            >
                {{ __( 'Esta integração não possui configurações adicionais.' ) }}
            </p>
        </div>
    </ModalDialog>
</template>
