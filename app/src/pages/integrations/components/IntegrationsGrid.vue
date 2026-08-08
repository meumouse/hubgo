<script setup>
/**
 * IntegrationsGrid.vue — the Integrations catalog with its toolbar.
 *
 * Cards are shown in a single flat grid by default; the user can group them
 * into stacked category sections or narrow the list to one category. Selecting
 * a single category always renders as one flat grid, so the display-mode switch
 * is disabled while a filter is active.
 *
 * @since 3.0.0
 */
import { computed, ref } from 'vue';
import { Grid, isMarkup, resolveIcon } from '../../../utils/icons';
import { __ } from '../../../utils/i18n';
import IntegrationCard from './IntegrationCard.vue';

const props = defineProps({
    cards: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    settings: { type: Object, default: () => ( {} ) },
    installingSlug: { type: String, default: '' },
});

defineEmits([ 'toggle', 'configure', 'install' ]);

const FALLBACK_CATEGORY = 'others';
const ALL = 'all';

const activeCategory = ref( ALL );
const displayMode = ref( 'flat' );

/**
 * Whether an integration's toggle is on.
 *
 * @param {string} key Setting key.
 * @return {boolean}
 */
function isEnabled( key ) {
    return 'yes' === ( props.settings[ key ] || 'no' );
}

/**
 * Category id of a card, defaulting to "others".
 *
 * @param {object} card Integration card.
 * @return {string}
 */
function categoryOf( card ) {
    return String( card?.category || '' ).trim() || FALLBACK_CATEGORY;
}

/**
 * Turn an unregistered category id into a readable label.
 *
 * @param {string} id Category id.
 * @return {string}
 */
function humanize( id ) {
    return String( id || '' )
        .replace( /[_-]+/g, ' ' )
        .replace( /\b\w/g, ( char ) => char.toUpperCase() );
}

const categoryMeta = computed( () => {
    const map = new Map();

    props.categories.forEach( ( category ) => {
        const id = String( category?.id || '' ).trim();

        if ( id && ! map.has( id ) ) {
            map.set( id, {
                id,
                label: String( category.label || humanize( id ) ),
                icon: String( category.icon || '' ),
            } );
        }
    } );

    return map;
} );

/**
 * Non-empty category sections, registered ones first (in catalog order).
 */
const allSections = computed( () => {
    const grouped = new Map();

    props.cards.forEach( ( card ) => {
        const id = categoryOf( card );

        if ( ! grouped.has( id ) ) {
            grouped.set( id, [] );
        }

        grouped.get( id ).push( card );
    } );

    const ordered = [];
    const used = new Set();

    categoryMeta.value.forEach( ( meta, id ) => {
        if ( grouped.has( id ) ) {
            ordered.push( { ...meta, cards: grouped.get( id ) } );
            used.add( id );
        }
    } );

    grouped.forEach( ( cards, id ) => {
        if ( ! used.has( id ) ) {
            ordered.push( { id, label: humanize( id ), icon: '', cards } );
        }
    } );

    return ordered;
} );

const filterChips = computed( () => [
    { id: ALL, label: __( 'Todas' ), icon: '', count: props.cards.length },
    ...allSections.value.map( ( section ) => ( {
        id: section.id,
        label: section.label,
        icon: section.icon,
        count: section.cards.length,
    } ) ),
] );

const visibleSections = computed( () => (
    ALL === activeCategory.value
        ? allSections.value
        : allSections.value.filter( ( section ) => section.id === activeCategory.value )
) );

const flatCards = computed( () => visibleSections.value.flatMap( ( section ) => section.cards ) );

const isGrouped = computed( () => 'grouped' === displayMode.value && ALL === activeCategory.value );

const activeSection = computed( () => visibleSections.value[ 0 ] || null );

const displayModes = computed( () => [
    { value: 'flat', label: __( 'Ver todas' ) },
    { value: 'grouped', label: __( 'Agrupar por categoria' ) },
] );
</script>

<template>
    <div>
        <div class="mb-8 flex flex-col gap-4 border-b border-slate-200 pb-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="chip in filterChips"
                    :key="chip.id"
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50"
                    :class="chip.id === activeCategory
                        ? 'border-primary-200 bg-primary-50 text-primary-800'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
                    :aria-pressed="chip.id === activeCategory"
                    @click="activeCategory = chip.id"
                >
                    <span
                        v-if="chip.id !== ALL && isMarkup( chip.icon )"
                        class="inline-flex [&_svg]:h-[18px] [&_svg]:w-[18px]"
                        v-html="chip.icon"
                    />
                    <component
                        :is="chip.id === ALL ? Grid : resolveIcon( chip.icon )"
                        v-else
                        width="18"
                        height="18"
                        aria-hidden="true"
                    />
                    <span>{{ chip.label }}</span>
                    <span
                        class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-semibold"
                        :class="chip.id === activeCategory ? 'bg-primary-100 text-primary-800' : 'bg-slate-100 text-slate-500'"
                    >
                        {{ chip.count }}
                    </span>
                </button>
            </div>

            <div
                class="inline-flex shrink-0 items-center gap-1 self-start rounded-full border border-slate-200 bg-white p-1 lg:self-auto"
                role="group"
                :aria-label="__( 'Modo de exibição' )"
            >
                <button
                    v-for="mode in displayModes"
                    :key="mode.value"
                    type="button"
                    class="cursor-pointer rounded-full border-0 px-4 py-1.5 text-sm font-medium transition focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    :class="mode.value === displayMode
                        ? 'bg-primary-700 text-white shadow-sm'
                        : 'bg-transparent text-slate-600 hover:text-primary-800'"
                    :aria-pressed="mode.value === displayMode"
                    :disabled="activeCategory !== ALL"
                    :title="activeCategory !== ALL ? __( 'Limpe o filtro de categoria para mudar o modo de exibição.' ) : ''"
                    @click="displayMode = mode.value"
                >
                    {{ mode.label }}
                </button>
            </div>
        </div>

        <!-- Grouped view: one stacked section per category -->
        <div v-if="isGrouped" class="space-y-12">
            <section v-for="section in visibleSections" :key="section.id">
                <header class="mb-6 flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-primary-50 text-primary-700 [&_svg]:h-[22px] [&_svg]:w-[22px]">
                        <span v-if="isMarkup( section.icon )" v-html="section.icon" />
                        <component :is="resolveIcon( section.icon )" v-else width="22" height="22" aria-hidden="true" />
                    </span>
                    <div>
                        <h3 class="m-0 text-lg font-semibold text-slate-700">{{ section.label }}</h3>
                        <p class="mb-0 mt-0.5 text-[13px] text-slate-500">
                            {{ section.cards.length }}
                            {{ section.cards.length === 1 ? __( 'integração' ) : __( 'integrações' ) }}
                        </p>
                    </div>
                </header>

                <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3 min-[1600px]:grid-cols-4">
                    <IntegrationCard
                        v-for="card in section.cards"
                        :key="card.slug"
                        :card="card"
                        :enabled="isEnabled( card.setting_key )"
                        :installing="installingSlug === card.slug"
                        @toggle="$emit( 'toggle', card.setting_key )"
                        @configure="$emit( 'configure', $event )"
                        @install="$emit( 'install', $event )"
                    />
                </div>
            </section>
        </div>

        <!-- Flat view: every visible card in a single grid -->
        <div v-else>
            <header v-if="activeCategory !== ALL && activeSection" class="mb-6 flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-primary-50 text-primary-700 [&_svg]:h-[22px] [&_svg]:w-[22px]">
                    <span v-if="isMarkup( activeSection.icon )" v-html="activeSection.icon" />
                    <component :is="resolveIcon( activeSection.icon )" v-else width="22" height="22" aria-hidden="true" />
                </span>
                <h3 class="m-0 text-lg font-semibold text-slate-700">{{ activeSection.label }}</h3>
            </header>

            <div v-if="flatCards.length" class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3 min-[1600px]:grid-cols-4">
                <IntegrationCard
                    v-for="card in flatCards"
                    :key="card.slug"
                    :card="card"
                    :enabled="isEnabled( card.setting_key )"
                    :installing="installingSlug === card.slug"
                    @toggle="$emit( 'toggle', card.setting_key )"
                    @configure="$emit( 'configure', $event )"
                    @install="$emit( 'install', $event )"
                />
            </div>

            <p v-else class="rounded-[8px] border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
                {{ __( 'Nenhuma integração disponível nesta categoria.' ) }}
            </p>
        </div>
    </div>
</template>
