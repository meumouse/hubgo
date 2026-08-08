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
        return __( 'Active' );
    }

    if ( license.value?.is_expired ) {
        return __( 'Expired' );
    }

    return hasKey.value ? __( 'Invalid' ) : __( 'Not activated' );
} );

const activationsLabel = computed( () => {
    const max = Number( license.value?.max_activations || 0 );
    const used = Number( license.value?.used_activations || 0 );

    if ( ! max ) {
        return used ? String( used ) : __( 'Not available' );
    }

    return `${ used }/${ max }`;
} );

const detailRows = computed( () => [
    { id: 'status', label: __( 'Status' ), value: statusLabel.value, badge: true },
    { id: 'plan', label: __( 'Plan' ), value: license.value?.plan_name || __( 'Not available' ) },
    { id: 'bundle', label: __( 'Bundle' ), value: license.value?.bundle?.name || __( 'Single license' ) },
    { id: 'key', label: __( 'License key' ), value: license.value?.masked_key || __( 'Not available' ) },
    { id: 'domain', label: __( 'Activated domain' ), value: license.value?.domain || __( 'Not available' ) },
    { id: 'expires', label: __( 'Expires on' ), value: formatDate( license.value?.expires_at ) },
    { id: 'support', label: __( 'Support until' ), value: formatDate( license.value?.support_expires_at ) },
    { id: 'activations', label: __( 'Activations' ), value: activationsLabel.value },
    { id: 'checked', label: __( 'Last check' ), value: formatTimestamp( license.value?.checked_at ) },
] );

const licenseField = {
    key: 'license_key',
    type: 'text',
    label: __( 'License key' ),
    placeholder: __( 'Paste the key you received with your purchase' ),
};

/**
 * Format an ISO-8601 date for display, treating an empty value as "lifetime".
 *
 * @param {string} value ISO date string.
 * @return {string}
 */
function formatDate( value ) {
    if ( ! value ) {
        return __( 'Lifetime' );
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
        return __( 'Not available' );
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
        loadError.value = error.message || __( 'Could not load the license data.' );
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
        toast( response.message || successMessage, 'success', __( 'License' ) );
    } catch ( error ) {
        applyLicense( error.response?.license );
        toast( error.message || __( 'Could not complete the operation.' ), 'error', __( 'Error' ) );
    } finally {
        busyAction.value = '';
    }
}

async function activate() {
    const key = form.license_key.trim();

    if ( ! key ) {
        toast( __( 'Enter the license key.' ), 'warning', __( 'License' ) );

        return;
    }

    await runAction( 'activate', 'license/activate', { license_key: key }, __( 'License activated successfully!' ) );

    if ( license.value?.is_active ) {
        form.license_key = '';
    }
}

async function sync() {
    await runAction( 'sync', 'license/sync', {}, __( 'License synchronized!' ) );
}

async function deactivate() {
    confirmOpen.value = false;

    await runAction( 'deactivate', 'license/deactivate', {}, __( 'License deactivated on this site.' ) );
}

onMounted( bootstrap );
</script>

<template>
    <div class="hubgo-license min-h-screen w-full">
        <PageShellSkeleton v-if="loading" />

        <div v-else class="w-full">
            <PageHeader :title="__( 'License' )">
                <template #description>
                    {{ __( 'Activate your license to receive automatic updates and unlock the Pro features. Questions? Visit our' ) }}
                    <a class="font-semibold text-primary-700 underline underline-offset-4" :href="docsUrl" target="_blank" rel="noreferrer">
                        {{ __( 'Help Center' ) }}
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
                    {{ __( 'The licensing service is not configured on this installation. Please contact support.' ) }}
                </div>

                <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,420px)]">
                    <!-- Details -->
                    <section class="rounded-[8px] bg-white p-6 shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100 lg:p-8">
                        <h2 class="m-0 text-[17px] font-semibold text-slate-800">{{ __( 'License details' ) }}</h2>
                        <p class="mb-0 mt-1 text-[13px] leading-5 text-slate-500">
                            {{ __( 'Information recorded on the last check with the server.' ) }}
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
                                {{ isActive ? __( 'Manage license' ) : __( 'Activate license' ) }}
                            </h2>
                            <p class="mb-0 mt-1 text-[13px] leading-5 text-slate-500">
                                {{ isActive
                                    ? __( 'Synchronize the data or release this activation to use the key on another site.' )
                                    : __( 'Enter the key you received with your purchase to unlock the updates and the Pro features.' ) }}
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
                                :title="__( 'Activate license' )"
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
                                :title="__( 'Synchronize' )"
                                color="outline"
                                :loading="busyAction === 'sync'"
                                :disabled="Boolean( busyAction )"
                                @click="sync"
                            />
                            <BaseButton
                                :title="__( 'Deactivate on this site' )"
                                color="danger"
                                :loading="busyAction === 'deactivate'"
                                :disabled="Boolean( busyAction )"
                                @click="confirmOpen = true"
                            />
                        </div>

                        <div class="mt-2 flex flex-col gap-2 border-t border-slate-100 pt-4 text-[13px]">
                            <a v-if="renewUrl" class="font-semibold text-primary-700 no-underline hover:underline" :href="renewUrl" target="_blank" rel="noreferrer">
                                {{ __( 'Renew subscription' ) }}
                            </a>
                            <a class="font-semibold text-primary-700 no-underline hover:underline" :href="purchaseUrl" target="_blank" rel="noreferrer">
                                {{ __( 'Buy a license' ) }}
                            </a>
                        </div>
                    </section>
                </div>
            </template>
        </div>

        <ConfirmDialog
            :open="confirmOpen"
            :title="__( 'Deactivate the license on this site?' )"
            :description="__( 'The site will stop receiving updates and the Pro features will be locked until a new activation.' )"
            :confirm-label="__( 'Yes, deactivate' )"
            :loading="busyAction === 'deactivate'"
            @confirm="deactivate"
            @cancel="confirmOpen = false"
        />

        <ToastStack :toasts="toasts" @dismiss="dismissToast" />
    </div>
</template>
