<script setup>
/**
 * DangerZone.vue — irreversible configuration actions.
 *
 * Owns only the confirmation flow; the actual reset is performed by the parent
 * so the page can refresh its reactive settings from the route response.
 *
 * @since 3.0.0
 */
import { ref } from 'vue';
import { __ } from '../../../../utils/i18n';
import BaseButton from '../../../../components/buttons/BaseButton.vue';
import ConfirmDialog from '../../../../components/modals/ConfirmDialog.vue';

defineProps({
    resetting: { type: Boolean, default: false },
});

const emit = defineEmits([ 'reset' ]);

const confirmOpen = ref( false );

/**
 * Ask for confirmation and forward the reset once granted.
 *
 * @return {void}
 */
function confirmReset() {
    confirmOpen.value = false;
    emit( 'reset' );
}
</script>

<template>
    <div class="py-6">
        <div class="flex flex-col gap-4 rounded-[8px] border border-rose-200 bg-rose-50 p-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="m-0 text-[15px] font-semibold text-rose-800">{{ __( 'Restaurar configurações padrão' ) }}</h3>
                <p class="mb-0 mt-1 max-w-2xl text-[13px] leading-5 text-rose-700">
                    {{ __( 'Todas as opções do HubGo voltarão aos valores originais. Códigos de rastreio dos pedidos não são afetados.' ) }}
                </p>
            </div>

            <BaseButton
                :title="__( 'Restaurar' )"
                color="danger"
                :loading="resetting"
                @click="confirmOpen = true"
            />
        </div>

        <ConfirmDialog
            :open="confirmOpen"
            :title="__( 'Restaurar configurações padrão?' )"
            :description="__( 'Esta ação não pode ser desfeita. Todas as configurações do HubGo voltarão aos valores originais.' )"
            :confirm-label="__( 'Sim, restaurar' )"
            :loading="resetting"
            @confirm="confirmReset"
            @cancel="confirmOpen = false"
        />
    </div>
</template>
