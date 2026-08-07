<script setup>
/**
 * SettingsPage.vue — HubGo settings screen.
 *
 * Hydrates from the hubgo/v1 REST bootstrap (schema + values), renders the
 * active section's cards through the shared field system and persists changes
 * through the sticky action bar.
 *
 * @since 3.0.0
 */
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { api, getBootstrapConfig } from '../../utils/api';
import { __ } from '../../utils/i18n';
import PageHeader from '../../components/layout/PageHeader.vue';
import SectionTabs from './components/SectionTabs.vue';
import SettingsActionBar from './components/SettingsActionBar.vue';
import FieldRow from '../../components/fields/FieldRow.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import PageShellSkeleton from '../../components/skeletons/PageShellSkeleton.vue';

const DOCS_URL = 'https://ajuda.meumouse.com/docs/hubgo/overview';
const ACTIVE_SECTION_STORAGE_KEY = 'hubgo-settings-active-section';

const loading = ref( true );
const saving = ref( false );
const loadError = ref( '' );

const schema = ref( [] );
const activeSectionId = ref( '' );
const settings = reactive( {} );
const savedSnapshot = ref( '{}' );
const version = ref( getBootstrapConfig().version || '' );

const toasts = ref( [] );
const toastTimers = new Map();
let toastSeed = 0;

const hasUnsavedChanges = computed( () => JSON.stringify( settings ) !== savedSnapshot.value );

const activeSection = computed(
    () => schema.value.find( ( section ) => section.id === activeSectionId.value ) || schema.value[ 0 ] || null,
);

watch( activeSectionId, ( value ) => {
    if ( value ) {
        window.localStorage.setItem( ACTIVE_SECTION_STORAGE_KEY, value );
    }
} );

/**
 * Restore the last visited section when it still exists in the schema.
 *
 * @param {Array} sections Schema sections.
 * @return {string}
 */
function resolveInitialSection( sections ) {
    const fallback = sections.length ? sections[ 0 ].id : '';
    const saved = window.localStorage.getItem( ACTIVE_SECTION_STORAGE_KEY );

    return saved && sections.some( ( section ) => section.id === saved ) ? saved : fallback;
}

function clearToastTimers( id ) {
    const timers = toastTimers.get( id );

    if ( ! timers ) {
        return;
    }

    window.clearTimeout( timers.hide );
    window.clearTimeout( timers.remove );
    toastTimers.delete( id );
}

/**
 * Push a toast onto the stack, scheduling its fade-out and removal.
 *
 * @param {string} message Body text.
 * @param {string} tone One of success|error|warning|info.
 * @param {string} title Header text.
 * @return {void}
 */
function toast( message, tone = 'info', title = __( 'HubGo' ) ) {
    const id = ++toastSeed;

    toasts.value.push( { id, title, message, tone, closing: false } );

    const hide = window.setTimeout( () => {
        const item = toasts.value.find( ( entry ) => entry.id === id );

        if ( item ) {
            item.closing = true;
        }
    }, 3000 );

    const remove = window.setTimeout( () => {
        toasts.value = toasts.value.filter( ( entry ) => entry.id !== id );
        toastTimers.delete( id );
    }, 3500 );

    toastTimers.set( id, { hide, remove } );
}

function dismissToast( id ) {
    clearToastTimers( id );
    toasts.value = toasts.value.filter( ( entry ) => entry.id !== id );
}

/**
 * Replace the reactive settings object and reset the dirty-check snapshot.
 *
 * @param {object} values Settings map.
 * @return {void}
 */
function applySettings( values ) {
    Object.keys( settings ).forEach( ( key ) => delete settings[ key ] );

    Object.entries( values || {} ).forEach( ( [ key, value ] ) => {
        settings[ key ] = value;
    } );

    savedSnapshot.value = JSON.stringify( settings );
}

async function bootstrap() {
    loading.value = true;
    loadError.value = '';

    try {
        const data = await api.get( 'settings' );

        schema.value = Array.isArray( data.schema ) ? data.schema : [];
        applySettings( data.settings || {} );
        activeSectionId.value = resolveInitialSection( schema.value );

        if ( data.version ) {
            version.value = data.version;
        }
    } catch ( error ) {
        loadError.value = error.message || __( 'Não foi possível carregar as configurações.' );
    } finally {
        loading.value = false;
    }
}

async function save() {
    if ( ! hasUnsavedChanges.value || saving.value ) {
        return;
    }

    saving.value = true;

    try {
        const response = await api.post( 'settings', { settings: { ...settings } } );

        applySettings( response.settings || settings );
        toast( response.message || __( 'Configurações salvas com sucesso!' ), 'success', __( 'Salvo' ) );
    } catch ( error ) {
        toast( error.message || __( 'Erro ao salvar as configurações.' ), 'error', __( 'Erro' ) );
    } finally {
        saving.value = false;
    }
}

onMounted( bootstrap );

onBeforeUnmount( () => {
    toastTimers.forEach( ( timers ) => {
        window.clearTimeout( timers.hide );
        window.clearTimeout( timers.remove );
    } );
    toastTimers.clear();
} );
</script>

<template>
    <div class="hubgo-settings min-h-screen w-full">
        <PageShellSkeleton v-if="loading" />

        <div v-else class="w-full">
            <PageHeader :title="__( 'Configurações' )">
                <template #description>
                    {{ __( 'Configure a calculadora de frete, o rastreio de pedidos e a aparência do HubGo. Se precisar de ajuda, acesse a nossa ' ) }}
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
                <SectionTabs
                    :sections="schema"
                    :active-section-id="activeSectionId"
                    @select="activeSectionId = $event"
                />

                <section class="mt-8 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
                    <div v-if="activeSection" class="space-y-10 px-6 py-10 lg:px-10 lg:py-12">
                        <div v-for="card in ( activeSection.cards || [] )" :key="card.id">
                            <div class="border-b border-slate-100 pb-4">
                                <h2 class="m-0 text-[17px] font-semibold text-slate-800">{{ card.title }}</h2>
                                <p v-if="card.description" class="mb-0 mt-1 max-w-3xl text-[13px] leading-5 text-slate-500">
                                    {{ card.description }}
                                </p>
                            </div>

                            <div class="divide-y divide-slate-100">
                                <FieldRow
                                    v-for="field in ( card.fields || [] )"
                                    :key="field.key"
                                    :field="field"
                                    :name="field.key"
                                    :model-value="settings[ field.key ]"
                                    @update:model-value="settings[ field.key ] = $event"
                                />
                            </div>
                        </div>
                    </div>

                    <SettingsActionBar
                        :saving="saving"
                        :has-unsaved-changes="hasUnsavedChanges"
                        @save="save"
                    />
                </section>
            </template>
        </div>

        <ToastStack :toasts="toasts" @dismiss="dismissToast" />
    </div>
</template>
