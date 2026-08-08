<script setup>
/**
 * LicensePage.vue — HubGo license screen.
 *
 * Owns the activation form and the license details panel, both driven by the
 * hubgo/v1 license routes, which wrap the MDS SDK license manager. Nothing here
 * talks to MDS directly.
 *
 * @since 3.0.0
 */
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../../utils/api';
import { __ } from '../../utils/i18n';
import { useToasts } from '../../composables/useToasts';
import PageHeader from '../../components/layout/PageHeader.vue';
import BaseButton from '../../components/buttons/BaseButton.vue';
import StatusBadge from '../../components/cards/StatusBadge.vue';
import ConfirmDialog from '../../components/modals/ConfirmDialog.vue';
import TextField from '../../components/fields/TextField.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import PageShellSkeleton from '../../components/skeletons/PageShellSkeleton.vue';

const loading = ref( true );
const loadError = ref( '' );
const busyAction = ref( '' );
const confirmOpen = ref( false );

const license = ref( {} );
const form = reactive( { license_key: '' } );

const { toasts, toast, dismissToast } = useToasts();

const isActive = computed( () => Boolean( license.value?.is_active ) );
const hasKey = computed( () => Boolean( license.value?.has_key ) );
const purchaseUrl = computed( () => license.value?.purchase_url || 'https://meumouse.com/plugins/hubgo/' );
const docsUrl = computed( () => license.value?.docs_url || 'https://ajuda.meumouse.com/docs/hubgo/overview' );
const renewUrl = computed( () => license.value?.renew_url || '' );

const statusTone = computed( () => {
    if ( isActive.value ) {
        return 'success';
    }

    return license.value?.is_expired ? 'warning' : 'danger';
} );

const statusLabel = computed( () => {
    if ( isActive.value ) {
        return __( 'Ativa' );
    }

    if ( license.value?.is_expired ) {
        return __( 'Expirada' );
    }

    return hasKey.value ? __( 'Inválida' ) : __( 'Não ativada' );
} );

const activationsLabel = computed( () => {
    const max = Number( license.value?.max_activations || 0 );
    const used = Number( license.value?.used_activations || 0 );

    if ( ! max ) {
        return used ? String( used ) : __( 'Não informado' );
    }

    return `${ used }/${ max }`;
} );

const detailRows = computed( () => [
    { id: 'status', label: __( 'Situação' ), value: statusLabel.value, badge: true },
    { id: 'plan', label: __( 'Plano' ), value: license.value?.plan_name || __( 'Não informado' ) },
    { id: 'bundle', label: __( 'Pacote' ), value: license.value?.bundle?.name || __( 'Licença individual' ) },
    { id: 'key', label: __( 'Chave de licença' ), value: license.value?.masked_key || __( 'Não informado' ) },
    { id: 'domain', label: __( 'Domínio ativado' ), value: license.value?.domain || __( 'Não informado' ) },
    { id: 'expires', label: __( 'Expira em' ), value: formatDate( license.value?.expires_at ) },
    { id: 'support', label: __( 'Suporte até' ), value: formatDate( license.value?.support_expires_at ) },
    { id: 'activations', label: __( 'Ativações' ), value: activationsLabel.value },
    { id: 'checked', label: __( 'Última verificação' ), value: formatTimestamp( license.value?.checked_at ) },
] );

const licenseField = {
    key: 'license_key',
    type: 'text',
    label: __( 'Chave de licença' ),
    placeholder: __( 'Cole aqui a chave recebida na compra' ),
};

/**
 * Format an ISO-8601 date for display, treating an empty value as "lifetime".
 *
 * @param {string} value ISO date string.
 * @return {string}
 */
function formatDate( value ) {
    if ( ! value ) {
        return __( 'Vitalícia' );
    }

    const date = new Date( value );

    return Number.isNaN( date.getTime() ) ? String( value ) : date.toLocaleDateString();
}

/**
 * Format a unix timestamp for display.
 *
 * @param {number} value Seconds since the epoch.
 * @return {string}
 */
function formatTimestamp( value ) {
    const seconds = Number( value || 0 );

    if ( ! seconds ) {
        return __( 'Não informado' );
    }

    return new Date( seconds * 1000 ).toLocaleString();
}

/**
 * Store a license payload returned by any of the routes.
 *
 * @param {object} payload License payload.
 * @return {void}
 */
function applyLicense( payload ) {
    if ( payload ) {
        license.value = payload;
    }
}

async function bootstrap() {
    loading.value = true;
    loadError.value = '';

    try {
        const data = await api.get( 'license' );

        applyLicense( data.license );
    } catch ( error ) {
        loadError.value = error.message || __( 'Não foi possível carregar os dados da licença.' );
    } finally {
        loading.value = false;
    }
}

/**
 * Call a license route, keeping the returned payload even on refusal.
 *
 * The routes answer a refused key with an HTTP error *and* the persisted
 * license payload, so the panel can show the new state and the reason at once.
 *
 * @param {string} action Action id used for the busy state.
 * @param {string} endpoint REST endpoint.
 * @param {object} body Request body.
 * @param {string} successMessage Fallback success message.
 * @return {Promise<void>}
 */
async function runAction( action, endpoint, body, successMessage ) {
    if ( busyAction.value ) {
        return;
    }

    busyAction.value = action;

    try {
        const response = await api.post( endpoint, body );

        applyLicense( response.license );
        toast( response.message || successMessage, 'success', __( 'Licença' ) );
    } catch ( error ) {
        applyLicense( error.response?.license );
        toast( error.message || __( 'Não foi possível concluir a operação.' ), 'error', __( 'Erro' ) );
    } finally {
        busyAction.value = '';
    }
}

async function activate() {
    const key = form.license_key.trim();

    if ( ! key ) {
        toast( __( 'Informe a chave de licença.' ), 'warning', __( 'Licença' ) );

        return;
    }

    await runAction( 'activate', 'license/activate', { license_key: key }, __( 'Licença ativada com sucesso!' ) );

    if ( license.value?.is_active ) {
        form.license_key = '';
    }
}

async function sync() {
    await runAction( 'sync', 'license/sync', {}, __( 'Licença sincronizada!' ) );
}

async function deactivate() {
    confirmOpen.value = false;

    await runAction( 'deactivate', 'license/deactivate', {}, __( 'Licença desativada neste site.' ) );
}

onMounted( bootstrap );
</script>

<template>
    <div class="hubgo-license min-h-screen w-full">
        <PageShellSkeleton v-if="loading" />

        <div v-else class="w-full">
            <PageHeader :title="__( 'Licença' )">
                <template #description>
                    {{ __( 'Ative sua licença para receber atualizações automáticas e liberar os recursos Pro. Dúvidas? Acesse a nossa ' ) }}
                    <a class="font-semibold text-primary-700 underline underline-offset-4" :href="docsUrl" target="_blank" rel="noreferrer">
                        {{ __( 'Central de Ajuda' ) }}
                    </a>
                </template>

                <template #actions>
                    <StatusBadge :label="statusLabel" :tone="statusTone" />
                </template>
            </PageHeader>

            <div v-if="loadError" class="mt-8 rounded-[8px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                {{ loadError }}
            </div>

            <template v-else>
                <div
                    v-if="! license.configured"
                    class="mt-8 rounded-[8px] border border-amber-200 bg-amber-50 px-5 py-4 text-[13px] leading-5 text-amber-800"
                >
                    {{ __( 'O serviço de licenciamento não está configurado nesta instalação. Entre em contato com o suporte.' ) }}
                </div>

                <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,420px)]">
                    <!-- Details -->
                    <section class="rounded-[8px] bg-white p-6 shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100 lg:p-8">
                        <h2 class="m-0 text-[17px] font-semibold text-slate-800">{{ __( 'Detalhes da licença' ) }}</h2>
                        <p class="mb-0 mt-1 text-[13px] leading-5 text-slate-500">
                            {{ __( 'Informações registradas na última verificação com o servidor.' ) }}
                        </p>

                        <dl class="mt-6 divide-y divide-slate-100">
                            <div v-for="row in detailRows" :key="row.id" class="flex items-center justify-between gap-6 py-3">
                                <dt class="m-0 text-[13px] font-medium text-slate-600">{{ row.label }}</dt>
                                <dd class="m-0 text-right">
                                    <StatusBadge v-if="row.badge" :label="row.value" :tone="statusTone" />
                                    <span v-else class="break-all text-[13px] font-semibold text-slate-800">{{ row.value }}</span>
                                </dd>
                            </div>
                        </dl>

                        <p
                            v-if="license.message"
                            class="mb-0 mt-6 rounded-[8px] border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] leading-5 text-slate-600"
                        >
                            {{ license.message }}
                        </p>
                    </section>

                    <!-- Actions -->
                    <section class="flex h-fit flex-col gap-4 rounded-[8px] bg-white p-6 shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100 lg:p-8">
                        <div>
                            <h2 class="m-0 text-[17px] font-semibold text-slate-800">
                                {{ isActive ? __( 'Gerenciar licença' ) : __( 'Ativar licença' ) }}
                            </h2>
                            <p class="mb-0 mt-1 text-[13px] leading-5 text-slate-500">
                                {{ isActive
                                    ? __( 'Sincronize os dados ou libere esta ativação para usar a chave em outro site.' )
                                    : __( 'Informe a chave recebida na compra para liberar as atualizações e os recursos Pro.' ) }}
                            </p>
                        </div>

                        <form v-if="! isActive" class="space-y-4" @submit.prevent="activate">
                            <div>
                                <label class="mb-2 block text-[13px] font-semibold text-slate-700" for="hubgo-license-key">
                                    {{ licenseField.label }}
                                </label>
                                <TextField
                                    input-id="hubgo-license-key"
                                    name="license_key"
                                    :field="licenseField"
                                    :model-value="form.license_key"
                                    @update:model-value="form.license_key = $event"
                                />
                            </div>

                            <BaseButton
                                :title="__( 'Ativar licença' )"
                                type="submit"
                                color="primary"
                                size="lg"
                                :loading="busyAction === 'activate'"
                                :disabled="Boolean( busyAction ) || ! license.configured"
                                @click="activate"
                            />
                        </form>

                        <div v-else class="flex flex-wrap gap-3">
                            <BaseButton
                                :title="__( 'Sincronizar' )"
                                color="outline"
                                :loading="busyAction === 'sync'"
                                :disabled="Boolean( busyAction )"
                                @click="sync"
                            />
                            <BaseButton
                                :title="__( 'Desativar neste site' )"
                                color="danger"
                                :loading="busyAction === 'deactivate'"
                                :disabled="Boolean( busyAction )"
                                @click="confirmOpen = true"
                            />
                        </div>

                        <div class="mt-2 flex flex-col gap-2 border-t border-slate-100 pt-4 text-[13px]">
                            <a v-if="renewUrl" class="font-semibold text-primary-700 no-underline hover:underline" :href="renewUrl" target="_blank" rel="noreferrer">
                                {{ __( 'Renovar assinatura' ) }}
                            </a>
                            <a class="font-semibold text-primary-700 no-underline hover:underline" :href="purchaseUrl" target="_blank" rel="noreferrer">
                                {{ __( 'Comprar uma licença' ) }}
                            </a>
                        </div>
                    </section>
                </div>
            </template>
        </div>

        <ConfirmDialog
            :open="confirmOpen"
            :title="__( 'Desativar a licença neste site?' )"
            :description="__( 'O site deixará de receber atualizações e os recursos Pro serão bloqueados até uma nova ativação.' )"
            :confirm-label="__( 'Sim, desativar' )"
            :loading="busyAction === 'deactivate'"
            @confirm="deactivate"
            @cancel="confirmOpen = false"
        />

        <ToastStack :toasts="toasts" @dismiss="dismissToast" />
    </div>
</template>
