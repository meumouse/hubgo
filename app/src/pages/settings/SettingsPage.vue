<script setup>
/**
 * SettingsPage.vue — HubGo settings screen.
 *
 * Hydrates from the hubgo/v1 REST bootstrap (schema + values), renders the
 * active section's cards through the shared field system and persists changes
 * through the sticky action bar.
 *
 * A card either declares `fields` — rendered by the field registry — or a
 * `component` name, resolved here against the page-local component map. That is
 * what lets the "About" tab mix plain settings with the system status panel and
 * the danger zone without breaking the schema-driven contract.
 *
 * @since 3.0.0
 * @version 3.1.0
 */
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { api, getBootstrapConfig } from '../../utils/api';
import { __ } from '../../utils/i18n';
import { cloneValue, deepEqual } from '../../utils/object';
import { withMinimumDuration } from '../../utils/async';
import { useToasts } from '../../composables/useToasts';
import PageHeader from '../../components/layout/PageHeader.vue';
import SectionTabs from './components/SectionTabs.vue';
import SettingsActionBar from './components/SettingsActionBar.vue';
import SystemStatusPanel from './components/cards/SystemStatusPanel.vue';
import DangerZone from './components/cards/DangerZone.vue';
import FieldRow from '../../components/fields/FieldRow.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import PageShellSkeleton from '../../components/skeletons/PageShellSkeleton.vue';

const DOCS_URL = 'https://ajuda.meumouse.com/docs/hubgo/overview';
const ACTIVE_SECTION_STORAGE_KEY = 'hubgo-settings-active-section';

const CARD_COMPONENTS = {
    'system-status': SystemStatusPanel,
    'danger-zone': DangerZone,
};

const loading = ref( true );
const saving = ref( false );
const resetting = ref( false );
const loadError = ref( '' );

const schema = ref( [] );
const activeSectionId = ref( '' );
const settings = reactive( {} );
const savedSettings = ref( {} );
const system = ref( [] );
const version = ref( getBootstrapConfig().version || '' );

const { toasts, toast, dismissToast } = useToasts();

const hasUnsavedChanges = computed( () => ! deepEqual( settings, savedSettings.value ) );

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

/**
 * Resolve the Vue component a `component` card asks for.
 *
 * @param {object} card Card definition.
 * @return {object|null}
 */
function componentFor( card ) {
    return card && card.component ? ( CARD_COMPONENTS[ card.component ] || null ) : null;
}

/**
 * Props for a `component` card. Bound per component so an unrelated prop never
 * leaks onto the rendered element as a stray attribute.
 *
 * @param {object} card Card definition.
 * @return {object}
 */
function componentPropsFor( card ) {
    if ( 'system-status' === card.component ) {
        return { rows: system.value };
    }

    if ( 'danger-zone' === card.component ) {
        return { resetting: resetting.value, onReset: resetSettings };
    }

    return {};
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

    savedSettings.value = cloneValue( settings );
}

async function bootstrap() {
    loading.value = true;
    loadError.value = '';

    try {
        const data = await api.get( 'settings' );

        schema.value = Array.isArray( data.schema ) ? data.schema : [];
        system.value = Array.isArray( data.system ) ? data.system : [];
        applySettings( data.settings || {} );
        activeSectionId.value = resolveInitialSection( schema.value );

        if ( data.version ) {
            version.value = data.version;
        }
    } catch ( error ) {
        loadError.value = error.message || __( 'Could not load the settings.' );
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
        // Held to a short floor so the button spinner is actually seen — a local
        // save otherwise resolves before the loading state paints.
        const response = await withMinimumDuration( api.post( 'settings', { settings: { ...settings } } ) );

        applySettings( response.settings || settings );
        toast( response.message || __( 'Settings saved successfully!' ), 'success', __( 'Saved' ) );
    } catch ( error ) {
        toast( error.message || __( 'Error saving the settings.' ), 'error', __( 'Error' ) );
    } finally {
        saving.value = false;
    }
}

/**
 * Restore every setting to its default value.
 *
 * @return {Promise<void>}
 */
async function resetSettings() {
    if ( resetting.value ) {
        return;
    }

    resetting.value = true;

    try {
        const response = await api.post( 'settings/reset', {} );

        applySettings( response.settings || {} );
        toast( response.message || __( 'Settings restored.' ), 'success', __( 'Restored' ) );
    } catch ( error ) {
        toast( error.message || __( 'Could not restore the settings.' ), 'error', __( 'Error' ) );
    } finally {
        resetting.value = false;
    }
}

onMounted( bootstrap );
</script>

<template>
    <div class="hubgo-settings min-h-screen w-full">
        <!--
            `mode="out-in"` rather than a straight crossfade: the skeleton mirrors
            the real shell, so the two overlapping would read as a double image
            of the same page rather than as a handover.
        -->
        <Transition name="hubgo-fade" mode="out-in">
        <PageShellSkeleton v-if="loading" />

        <div v-else class="w-full">
            <PageHeader :title="__( 'Settings' )">
                <template #description>
                    {{ __( 'Set up the shipping calculator, the order tracking and the HubGo appearance. If you need help, visit our' ) }}
                    <a class="font-semibold text-primary-700 underline underline-offset-4" :href="DOCS_URL" target="_blank" rel="noreferrer">
                        {{ __( 'Help Center' ) }}
                    </a>
                </template>

                <template #actions>
                    <span
                        v-if="version"
                        class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-800"
                    >
                        {{ __( 'Version' ) }} {{ version }}
                    </span>
                </template>
            </PageHeader>

            <Transition name="hubgo-fade">
                <div v-if="loadError" class="mt-8 rounded-[8px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                    {{ loadError }}
                </div>
            </Transition>

            <template v-if="! loadError">
                <SectionTabs
                    :sections="schema"
                    :active-section-id="activeSectionId"
                    @select="activeSectionId = $event"
                />

                <section class="mt-8 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
                    <!--
                        Keyed on the section id so switching tabs is a swap, not a
                        re-render in place: without the key Vue patches the same
                        node and the transition never fires.
                    -->
                    <Transition name="hubgo-panel" mode="out-in">
                    <div v-if="activeSection" :key="activeSection.id" class="space-y-10 px-6 py-10 lg:px-10 lg:py-12">
                        <div v-for="card in ( activeSection.cards || [] )" :key="card.id">
                            <div class="border-b border-slate-100 pb-4">
                                <h2 class="m-0 text-[17px] font-semibold text-slate-800">{{ card.title }}</h2>
                                <p v-if="card.description" class="mb-0 mt-1 max-w-3xl text-[13px] leading-5 text-slate-500">
                                    {{ card.description }}
                                </p>
                            </div>

                            <component
                                :is="componentFor( card )"
                                v-if="componentFor( card )"
                                v-bind="componentPropsFor( card )"
                            />

                            <div v-else class="divide-y divide-slate-100">
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
                    </Transition>

                    <SettingsActionBar
                        :saving="saving"
                        :has-unsaved-changes="hasUnsavedChanges"
                        @save="save"
                    />
                </section>
            </template>
        </div>
        </Transition>

        <ToastStack :toasts="toasts" @dismiss="dismissToast" />
    </div>
</template>
