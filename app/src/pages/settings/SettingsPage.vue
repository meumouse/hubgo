<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { api } from '../../utils/api';
import { __ } from '../../utils/i18n';
import SectionTabs from './components/SectionTabs.vue';
import SettingsActionBar from './components/SettingsActionBar.vue';
import FieldRenderer from '../../components/fields/FieldRenderer.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';

const loading = ref( true );
const saving = ref( false );
const loadError = ref( '' );

const schema = ref( [] );
const activeTab = ref( '' );
const settings = reactive( {} );
const savedSnapshot = ref( '{}' );

const toasts = ref( [] );
let toastSeed = 0;

const hasUnsavedChanges = computed( () => JSON.stringify( settings ) !== savedSnapshot.value );

const activeSection = computed(
    () => schema.value.find( ( section ) => section.id === activeTab.value ) || schema.value[ 0 ] || null,
);

function pushToast( message, tone = 'info', title = 'HubGo' ) {
    const id = ++toastSeed;
    toasts.value.push( { id, title, message, tone, closing: false } );

    setTimeout( () => {
        const item = toasts.value.find( ( t ) => t.id === id );
        if ( item ) item.closing = true;
    }, 3000 );

    setTimeout( () => {
        toasts.value = toasts.value.filter( ( t ) => t.id !== id );
    }, 3500 );
}

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
        activeTab.value = schema.value.length ? schema.value[ 0 ].id : '';
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
        pushToast( response.message || __( 'Configurações salvas com sucesso!' ), 'success' );
    } catch ( error ) {
        pushToast( error.message || __( 'Erro ao salvar as configurações.' ), 'danger' );
    } finally {
        saving.value = false;
    }
}

onMounted( bootstrap );
</script>

<template>
    <div class="mx-auto max-w-4xl px-1 py-6">
        <header class="mb-6 flex items-center gap-3">
            <h1 class="text-xl font-bold text-slate-900">HubGo</h1>
            <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">
                {{ __( 'Gerenciamento de Frete' ) }}
            </span>
        </header>

        <div v-if="loading" class="space-y-3">
            <div class="hubgo-skeleton h-10 w-full" />
            <div class="hubgo-skeleton h-40 w-full" />
        </div>

        <div v-else-if="loadError" class="rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            {{ loadError }}
        </div>

        <div v-else>
            <SectionTabs :tabs="schema.map( ( s ) => ( { id: s.id, label: s.title, icon: s.icon } ) )" :active="activeTab" @select="activeTab = $event" />

            <div v-if="activeSection" class="mt-6 space-y-6">
                <section
                    v-for="card in ( activeSection.cards || [] )"
                    :key="card.id"
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="mb-2">
                        <h2 class="text-base font-semibold text-slate-900">{{ card.title }}</h2>
                        <p v-if="card.description" class="mt-1 text-sm text-slate-500">{{ card.description }}</p>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <FieldRenderer
                            v-for="field in ( card.fields || [] )"
                            :key="field.key"
                            :field="field"
                            :model-value="settings[ field.key ]"
                            @update:model-value="settings[ field.key ] = $event"
                        />
                    </div>
                </section>
            </div>

            <SettingsActionBar :saving="saving" :has-unsaved-changes="hasUnsavedChanges" @save="save" />
        </div>

        <ToastStack :toasts="toasts" />
    </div>
</template>
