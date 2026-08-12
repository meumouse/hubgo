<script setup>
/**
 * LicensePage.vue — HubGo license screen.
 *
 * Owns the activation form and the license details panel, both driven by the
 * hubgo/v1 license routes, which wrap the MDS SDK license manager. Nothing here
 * talks to MDS directly.
 *
 * The screen is a single white panel that swaps its whole body with the license
 * state: an activation form beside the "what you unlock" summary while the key
 * is missing or refused, the detail rows beside the account status once it is
 * active. Both halves read from the same payload — nothing is state-specific
 * beyond the copy.
 *
 * @since 3.0.0
 * @version 3.1.0
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
    { id: 'status', label: __( 'License status' ), value: statusLabel.value, badge: true },
    { id: 'plan', label: __( 'Subscription' ), value: license.value?.plan_name || __( 'Not available' ) },
    { id: 'bundle', label: __( 'Bundle' ), value: license.value?.bundle?.name || __( 'Single license' ) },
    { id: 'expires', label: __( 'Expires on' ), value: formatDate( license.value?.expires_at ) },
    { id: 'support', label: __( 'Support until' ), value: formatDate( license.value?.support_expires_at ) },
    { id: 'activations', label: __( 'Activations' ), value: activationsLabel.value },
    { id: 'domain', label: __( 'Activated domain' ), value: license.value?.domain || __( 'Not available' ) },
    { id: 'checked', label: __( 'Last check' ), value: formatTimestamp( license.value?.checked_at ) },
    { id: 'key', label: __( 'Your license key' ), value: license.value?.masked_key || __( 'Not available' ) },
] );

const panelTitle = computed( () => ( isActive.value ? __( 'License details' ) : __( 'Activate license' ) ) );

const panelDescription = computed( () => ( isActive.value
    ? __( 'Check the current status of your license and update it whenever necessary.' )
    : __( 'Enter your license key to unlock the Pro features.' ) ) );

/** Short sentence shown next to the status badge on the account panel. */
const statusHeadline = computed( () => {
    if ( isActive.value ) {
        return __( 'Your installation is unlocked for full use.' );
    }

    if ( license.value?.is_expired ) {
        return __( 'Your license expired and the Pro features are locked.' );
    }

    return hasKey.value
        ? __( 'The stored key was refused by the server.' )
        : __( 'No license key is active on this site yet.' );
} );

/** Supporting paragraph below the status headline. */
const statusHelp = computed( () => ( isActive.value
    ? __( 'Your license is active. You can keep it synchronized here whenever the status changes on the server.' )
    : __( 'Activate a key to receive automatic updates and to unlock every Pro integration.' ) ) );

const unlockItems = computed( () => [
    __( 'Automatic updates and license synchronization' ),
    __( 'Pro integrations: Joinotify and the Elementor widget' ),
    __( 'Support, access and subscription updates' ),
] );

const licenseField = {
    key: 'license_key',
    type: 'text',
    label: __( 'License key' ),
    placeholder: __( 'Example: CM-0000-0000-0000' ),
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
        <Transition name="hubgo-fade" mode="out-in">
        <PageShellSkeleton v-if="loading" />

        <div v-else class="w-full">
            <PageHeader :title="__( 'License' )">
                <template #description>
                    {{ __( 'Activate your license to receive automatic updates and unlock the Pro features. Questions? Visit our' ) }}
                    <a class="font-semibold text-primary-700 underline underline-offset-4" :href="docsUrl" target="_blank" rel="noreferrer">
                        {{ __( 'Help Center' ) }}
                    </a>
                </template>
            </PageHeader>

            <Transition name="hubgo-fade">
                <div v-if="loadError" class="mt-8 rounded-[8px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                    {{ loadError }}
                </div>
            </Transition>

            <template v-if="! loadError">
                <div
                    v-if="! license.configured"
                    class="mt-8 rounded-[8px] border border-amber-200 bg-amber-50 px-5 py-4 text-[13px] leading-5 text-amber-800"
                >
                    {{ __( 'The licensing service is not configured on this installation. Please contact support.' ) }}
                </div>

                <section class="mt-8 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
                    <div class="px-6 py-10 lg:px-10 lg:py-12">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="m-0 text-xs font-semibold uppercase tracking-[0.2em] text-shell-500">
                                    {{ __( 'License' ) }}
                                </p>
                                <h2 class="mb-0 mt-1 text-xl font-semibold text-ink">{{ panelTitle }}</h2>
                                <p class="mb-0 mt-2 max-w-2xl text-[13px] leading-5 text-muted">{{ panelDescription }}</p>
                            </div>

                            <BaseButton
                                v-if="! isActive"
                                :title="__( 'Buy a license' )"
                                :href="purchaseUrl"
                                color="white"
                                class="shrink-0"
                                target="_blank"
                                rel="noreferrer"
                            />
                        </div>

                        <!--
                            Activating swaps the whole body — form and upsell out,
                            details and account status in. `mode="out-in"` keeps the
                            two layouts from overlapping mid-swap.
                        -->
                        <Transition name="hubgo-panel" mode="out-in">
                        <div
                            v-if="isActive"
                            key="details"
                            class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,420px)]"
                        >
                            <!-- Details -->
                            <section class="rounded-[8px] border border-slate-200 p-6">
                                <h3 class="m-0 text-[17px] font-semibold text-slate-800">{{ __( 'License information' ) }}</h3>

                                <dl class="mt-4 divide-y divide-slate-100">
                                    <div v-for="row in detailRows" :key="row.id" class="flex items-center justify-between gap-6 py-3">
                                        <dt class="m-0 text-[13px] font-medium text-slate-600">{{ row.label }}</dt>
                                        <dd class="m-0 text-right">
                                            <StatusBadge v-if="row.badge" :label="row.value" :tone="statusTone" />
                                            <span v-else class="break-all text-[13px] font-semibold text-slate-800">{{ row.value }}</span>
                                        </dd>
                                    </div>
                                </dl>

                                <div class="mt-6 flex flex-wrap gap-3">
                                    <BaseButton
                                        :title="__( 'Deactivate license' )"
                                        color="danger"
                                        :loading="busyAction === 'deactivate'"
                                        :disabled="Boolean( busyAction )"
                                        @click="confirmOpen = true"
                                    />
                                    <BaseButton
                                        :title="__( 'Synchronize license' )"
                                        color="white"
                                        :loading="busyAction === 'sync'"
                                        :disabled="Boolean( busyAction )"
                                        @click="sync"
                                    />
                                </div>

                                <Transition name="hubgo-fade">
                                    <p
                                        v-if="license.message"
                                        class="mb-0 mt-6 rounded-[8px] border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] leading-5 text-slate-600"
                                    >
                                        {{ license.message }}
                                    </p>
                                </Transition>
                            </section>

                            <!-- Account status -->
                            <aside class="h-fit rounded-[8px] border border-slate-200 p-6">
                                <p class="m-0 text-xs font-semibold uppercase tracking-[0.2em] text-shell-500">
                                    {{ __( 'Account status' ) }}
                                </p>

                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <StatusBadge :label="statusLabel" :tone="statusTone" />
                                    <span class="text-[13px] leading-5 text-slate-700">{{ statusHeadline }}</span>
                                </div>

                                <p class="mb-0 mt-4 text-[13px] leading-5 text-slate-500">{{ statusHelp }}</p>

                                <div class="mt-5 rounded-[8px] bg-slate-50 p-4">
                                    <h4 class="m-0 text-[14px] font-semibold text-slate-800">{{ __( 'Quick help' ) }}</h4>
                                    <p class="mb-0 mt-2 text-[13px] leading-5 text-slate-500">
                                        {{ __( 'If the license does not update right away, click Synchronize to fetch the latest status from the server.' ) }}
                                    </p>

                                    <div class="mt-3 flex flex-col gap-2 text-[13px]">
                                        <a
                                            class="font-semibold text-primary-700 no-underline hover:underline"
                                            :href="docsUrl"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            {{ __( 'Open the Help Center' ) }}
                                        </a>
                                        <a
                                            v-if="renewUrl"
                                            class="font-semibold text-primary-700 no-underline hover:underline"
                                            :href="renewUrl"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            {{ __( 'Renew subscription' ) }}
                                        </a>
                                    </div>
                                </div>
                            </aside>
                        </div>

                        <div
                            v-else
                            key="activate"
                            class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,420px)]"
                        >
                            <!-- Activation -->
                            <section class="rounded-[8px] border border-slate-200 p-6">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <h3 class="m-0 text-[17px] font-semibold text-slate-800">{{ __( 'Activate license' ) }}</h3>
                                    <StatusBadge :label="statusLabel" :tone="statusTone" />
                                </div>

                                <p class="mb-0 mt-2 text-[13px] leading-5 text-slate-500">
                                    {{ __( 'Paste the license key you received with your purchase and click Activate to unlock every feature.' ) }}
                                </p>

                                <form class="mt-5 space-y-4" @submit.prevent="activate">
                                    <div>
                                        <label class="sr-only" for="hubgo-license-key">{{ licenseField.label }}</label>
                                        <TextField
                                            input-id="hubgo-license-key"
                                            name="license_key"
                                            :field="licenseField"
                                            :model-value="form.license_key"
                                            @update:model-value="form.license_key = $event"
                                        />
                                    </div>

                                    <div class="flex flex-wrap gap-3">
                                        <BaseButton
                                            :title="__( 'Activate license' )"
                                            type="submit"
                                            color="primary"
                                            :loading="busyAction === 'activate'"
                                            :disabled="Boolean( busyAction ) || ! license.configured"
                                            @click="activate"
                                        />
                                        <BaseButton
                                            :title="__( 'Buy a license' )"
                                            :href="purchaseUrl"
                                            color="success"
                                            target="_blank"
                                            rel="noreferrer"
                                        />
                                    </div>
                                </form>

                                <Transition name="hubgo-fade">
                                    <p
                                        v-if="license.message"
                                        class="mb-0 mt-5 rounded-[8px] border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] leading-5 text-slate-600"
                                    >
                                        {{ license.message }}
                                    </p>
                                </Transition>
                            </section>

                            <!-- What the license unlocks -->
                            <aside class="h-fit rounded-[8px] border border-dashed border-slate-300 p-6">
                                <p class="m-0 text-xs font-semibold uppercase tracking-[0.2em] text-shell-500">
                                    {{ __( 'What you unlock' ) }}
                                </p>

                                <h3 class="mb-0 mt-2 text-[17px] font-semibold text-slate-800">
                                    {{ __( 'Activate the license to unlock the HubGo Pro features.' ) }}
                                </h3>

                                <ul class="m-0 mt-4 list-none space-y-3 p-0">
                                    <li v-for="item in unlockItems" :key="item" class="flex items-start gap-3 text-[13px] leading-5 text-slate-600">
                                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-700" aria-hidden="true" />
                                        <span>{{ item }}</span>
                                    </li>
                                </ul>

                                <p class="mb-0 mt-5 text-[13px] leading-5 text-slate-500">{{ statusHelp }}</p>
                            </aside>
                        </div>
                        </Transition>
                    </div>
                </section>
            </template>
        </div>
        </Transition>

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
