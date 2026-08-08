<script setup>
/**
 * IntegrationsPage.vue — HubGo integrations screen.
 *
 * Hydrates from GET hubgo/v1/integrations and persists through the same
 * POST hubgo/v1/settings route the settings screen uses: integration toggles
 * and their fields live in the very same `hubgo_settings` option, so there is
 * one save path and one sanitizer for everything.
 *
 * @since 3.0.0
 */
import { computed, onMounted, reactive, ref } from 'vue';
import { api, getBootstrapConfig } from '../../utils/api';
import { __ } from '../../utils/i18n';
import { cloneValue, deepEqual } from '../../utils/object';
import { useToasts } from '../../composables/useToasts';
import PageHeader from '../../components/layout/PageHeader.vue';
import IntegrationsGrid from './components/IntegrationsGrid.vue';
import IntegrationSettingsModal from '../../components/modals/IntegrationSettingsModal.vue';
import SettingsActionBar from '../settings/components/SettingsActionBar.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import PageShellSkeleton from '../../components/skeletons/PageShellSkeleton.vue';

const DOCS_URL = 'https://ajuda.meumouse.com/docs/hubgo/overview';

const loading = ref( true );
const saving = ref( false );
const loadError = ref( '' );
const installingSlug = ref( '' );

const cards = ref( [] );
const categories = ref( [] );
const settings = reactive( {} );
const savedSettings = ref( {} );
const license = ref( {} );
const version = ref( getBootstrapConfig().version || '' );

const selectedSlug = ref( '' );
const modalOpen = ref( false );

const { toasts, toast, dismissToast } = useToasts();

const hasUnsavedChanges = computed( () => ! deepEqual( settings, savedSettings.value ) );
const licenseUrl = computed( () => license.value?.url || getBootstrapConfig().pages?.license || '' );
const showLicenseNotice = computed( () => Boolean( license.value ) && ! license.value.is_active );

const selectedIntegration = computed(
    () => cards.value.find( ( card ) => card.slug === selectedSlug.value ) || null,
);

/**
 * Replace the reactive settings map and reset the dirty-check snapshot.
 *
 * @param {object} values Settings map.
 * @return {void}
 */
function applySettings( values ) {
    Object.keys( settings ).forEach( ( key ) => delete settings[ key ] );

    Object.entries( values || {} ).forEach( ( [ key, value ] ) => {
        settings[ key ] = value;
    } );

    savedSettings.value = cloneValue( settings );
}

async function bootstrap() {
    loading.value = true;
    loadError.value = '';

    try {
        const data = await api.get( 'integrations' );

        cards.value = Array.isArray( data.cards ) ? data.cards : [];
        categories.value = Array.isArray( data.categories ) ? data.categories : [];
        license.value = data.license || {};
        applySettings( data.settings || {} );

        if ( data.version ) {
            version.value = data.version;
        }
    } catch ( error ) {
        loadError.value = error.message || __( 'Não foi possível carregar as integrações.' );
    } finally {
        loading.value = false;
    }
}

/**
 * Flip an integration toggle.
 *
 * @param {string} key Setting key of the integration.
 * @return {void}
 */
function toggleIntegration( key ) {
    if ( ! key ) {
        return;
    }

    settings[ key ] = 'yes' === settings[ key ] ? 'no' : 'yes';
}

/**
 * Update one field inside the settings modal.
 *
 * @param {string} key Field key.
 * @param {*} value New value.
 * @return {void}
 */
function updateSetting( key, value ) {
    settings[ key ] = value;
}

/**
 * Open the settings modal of an integration.
 *
 * @param {string} slug Integration slug.
 * @return {void}
 */
function openConfig( slug ) {
    selectedSlug.value = slug;
    modalOpen.value = true;
}

function closeConfig() {
    modalOpen.value = false;
    selectedSlug.value = '';
}

async function save() {
    if ( ! hasUnsavedChanges.value || saving.value ) {
        return;
    }

    saving.value = true;

    try {
        const response = await api.post( 'settings', { settings: { ...settings } } );

        applySettings( response.settings || settings );
        toast( response.message || __( 'Integrações salvas com sucesso!' ), 'success', __( 'Salvo' ) );

        // A toggle can change what the cards may do (a disabled integration
        // hides its settings), so refresh the catalog from the server.
        await refreshCards();
    } catch ( error ) {
        toast( error.message || __( 'Erro ao salvar as integrações.' ), 'error', __( 'Erro' ) );
    } finally {
        saving.value = false;
    }
}

/**
 * Re-read the catalog without touching the pending settings edits.
 *
 * @return {Promise<void>}
 */
async function refreshCards() {
    try {
        const data = await api.get( 'integrations' );

        cards.value = Array.isArray( data.cards ) ? data.cards : cards.value;
        license.value = data.license || license.value;
    } catch ( error ) {
        // A stale catalog is harmless: the values the user just saved are
        // already persisted, so there is nothing to surface here.
    }
}

/**
 * Install and activate the plugin an integration depends on.
 *
 * @param {string} slug Integration slug.
 * @return {Promise<void>}
 */
async function installPlugin( slug ) {
    if ( installingSlug.value ) {
        return;
    }

    installingSlug.value = slug;

    try {
        const response = await api.post( 'plugins/install', { slug } );

        cards.value = Array.isArray( response.cards ) ? response.cards : cards.value;
        toast( response.message || __( 'Plugin instalado com sucesso!' ), 'success', __( 'Instalado' ) );
    } catch ( error ) {
        toast( error.message || __( 'Não foi possível instalar o plugin.' ), 'error', __( 'Erro' ) );
    } finally {
        installingSlug.value = '';
    }
}

onMounted( bootstrap );
</script>

<template>
    <div class="hubgo-integrations min-h-screen w-full">
        <PageShellSkeleton v-if="loading" />

        <div v-else class="w-full">
            <PageHeader :title="__( 'Integrações' )">
                <template #description>
                    {{ __( 'Conecte o HubGo a outros plugins e serviços de logística. Se precisar de ajuda, acesse a nossa ' ) }}
                    <a class="font-semibold text-primary-700 underline underline-offset-4" :href="DOCS_URL" target="_blank" rel="noreferrer">
                        {{ __( 'Central de Ajuda' ) }}
                    </a>
                </template>

                <template #actions>
                    <span
                        v-if="version"
                        class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-800"
                    >
                        {{ __( 'Versão' ) }} {{ version }}
                    </span>
                </template>
            </PageHeader>

            <div v-if="loadError" class="mt-8 rounded-[8px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                {{ loadError }}
            </div>

            <template v-else>
                <div
                    v-if="showLicenseNotice"
                    class="mt-8 flex flex-wrap items-center justify-between gap-3 rounded-[8px] border border-amber-200 bg-amber-50 px-5 py-4 text-[13px] leading-5 text-amber-800"
                >
                    <span>{{ __( 'As integrações marcadas como Pro exigem uma licença ativa do HubGo.' ) }}</span>
                    <a
                        v-if="licenseUrl"
                        class="font-semibold text-amber-900 underline underline-offset-4"
                        :href="licenseUrl"
                    >
                        {{ __( 'Ativar licença' ) }}
                    </a>
                </div>

                <section class="mt-8 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
                    <div class="px-6 py-10 lg:px-10 lg:py-12">
                        <IntegrationsGrid
                            :cards="cards"
                            :categories="categories"
                            :settings="settings"
                            :installing-slug="installingSlug"
                            @toggle="toggleIntegration"
                            @configure="openConfig"
                            @install="installPlugin"
                        />
                    </div>

                    <SettingsActionBar
                        :saving="saving"
                        :has-unsaved-changes="hasUnsavedChanges"
                        @save="save"
                    />
                </section>
            </template>
        </div>

        <IntegrationSettingsModal
            :open="modalOpen && !! selectedIntegration"
            :integration="selectedIntegration"
            :settings="settings"
            @close="closeConfig"
            @update-setting="updateSetting"
        />

        <ToastStack :toasts="toasts" @dismiss="dismissToast" />
    </div>
</template>
