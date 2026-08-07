<script setup>
/**
 * ConfirmDialog.vue — confirmation prompt for destructive/irreversible actions.
 *
 * @since 3.0.0
 */
import { __ } from '../../utils/i18n';
import BaseButton from '../buttons/BaseButton.vue';
import ModalDialog from './ModalDialog.vue';

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    confirmLabel: { type: String, default: () => __( 'Confirmar' ) },
    loading: { type: Boolean, default: false },
});

defineEmits([ 'confirm', 'cancel' ]);
</script>

<template>
    <ModalDialog
        :open="open"
        :title="title"
        :description="description"
        :eyebrow="__( 'Confirmação' )"
        size-class="max-w-lg"
        @close="$emit( 'cancel' )"
    >
        <div class="flex items-center justify-end gap-3">
            <BaseButton
                :title="__( 'Cancelar' )"
                color="white"
                :disabled="loading"
                @click="$emit( 'cancel' )"
            />
            <BaseButton
                :title="confirmLabel"
                color="danger"
                :loading="loading"
                @click="$emit( 'confirm' )"
            />
        </div>
    </ModalDialog>
</template>
