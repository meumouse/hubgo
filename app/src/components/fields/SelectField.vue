<script setup>
/**
 * SelectField.vue
 *
 * Custom listbox replacing the native <select>. The dropdown is teleported to
 * <body> and positioned with fixed coordinates so it escapes any ancestor
 * `overflow` clipping (settings cards, modals). Supports keyboard navigation and
 * an optional search box (auto-enabled past 8 options).
 *
 * @since 3.0.0
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { __ } from '../../utils/i18n';

const props = defineProps({
    modelValue: { type: [ String, Number ], default: '' },
    field: { type: Object, required: true },
    name: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits([ 'update:modelValue' ]);

const rootEl = ref( null );
const dropdownEl = ref( null );
const searchEl = ref( null );
const isOpen = ref( false );
const query = ref( '' );
const activeIndex = ref( 0 );
const dropdownStyle = ref( {} );

const uid = `hubgo-select-${ Math.random().toString( 36 ).slice( 2, 10 ) }`;
const buttonId = `${ uid }-button`;
const listboxId = `${ uid }-listbox`;

const options = computed( () => ( Array.isArray( props.field.options ) ? props.field.options : [] ) );
const showSearch = computed( () => Boolean( props.field.searchable ) || options.value.length > 8 );

const filteredOptions = computed( () => {
    const list = options.value.map( ( option ) => ( {
        value: option.value,
        label: option.label,
        meta: option.meta || '',
    } ) );

    if ( ! query.value ) {
        return list;
    }

    const needle = query.value.toLowerCase();

    return list.filter( ( option ) =>
        String( option.label || '' ).toLowerCase().includes( needle )
        || String( option.meta || '' ).toLowerCase().includes( needle ),
    );
} );

const showEmpty = computed( () => filteredOptions.value.length === 0 );

const selectedOption = computed(
    () => options.value.find( ( option ) => String( option.value ) === String( props.modelValue ) ) || null,
);

const selectedLabel = computed(
    () => selectedOption.value?.label || props.field.placeholder || __( 'Selecione uma opção' ),
);

watch( isOpen, ( value ) => {
    if ( ! value ) {
        stopTracking();

        return;
    }

    query.value = '';
    activeIndex.value = Math.max( 0, filteredOptions.value.findIndex( isSelected ) );

    updatePosition();
    startTracking();

    window.requestAnimationFrame( () => {
        updatePosition();

        if ( showSearch.value && searchEl.value ) {
            searchEl.value.focus();
        }
    } );
} );

watch( filteredOptions, ( value ) => {
    if ( ! value.length ) {
        activeIndex.value = 0;

        return;
    }

    const selectedIndex = value.findIndex( isSelected );
    activeIndex.value = selectedIndex >= 0 ? selectedIndex : 0;
} );

function toggle() {
    if ( props.disabled ) {
        return;
    }

    isOpen.value = ! isOpen.value;
}

function openAndFocus( index ) {
    if ( props.disabled ) {
        return;
    }

    isOpen.value = true;
    activeIndex.value = normalizeIndex( index );
}

function close() {
    isOpen.value = false;
}

function normalizeIndex( index ) {
    if ( ! filteredOptions.value.length ) {
        return 0;
    }

    return Math.max( 0, Math.min( index, filteredOptions.value.length - 1 ) );
}

function isSelected( option ) {
    return String( option.value ) === String( props.modelValue );
}

function selectOption( option ) {
    emit( 'update:modelValue', option.value );
    close();
}

function moveActive( delta ) {
    if ( filteredOptions.value.length ) {
        activeIndex.value = normalizeIndex( activeIndex.value + delta );
    }
}

function commitActive() {
    const option = filteredOptions.value[ activeIndex.value ];

    if ( option ) {
        selectOption( option );
    }
}

function optionClass( index, option ) {
    if ( index === activeIndex.value ) {
        return 'bg-primary-50 text-slate-800';
    }

    if ( isSelected( option ) ) {
        return 'bg-primary-50/70';
    }

    return 'hover:bg-slate-50';
}

function handleOutsideClick( event ) {
    if ( ! rootEl.value ) {
        return;
    }

    const insideRoot = rootEl.value.contains( event.target );
    const insideDropdown = dropdownEl.value && dropdownEl.value.contains( event.target );

    if ( ! insideRoot && ! insideDropdown ) {
        close();
    }
}

/**
 * Position the teleported dropdown next to the trigger, flipping upwards when
 * there is not enough room below.
 *
 * @return {void}
 */
function updatePosition() {
    if ( ! rootEl.value || typeof window === 'undefined' ) {
        return;
    }

    const rect = rootEl.value.getBoundingClientRect();
    const gap = 8;
    const cap = 320;
    const viewportHeight = window.innerHeight;
    const spaceBelow = viewportHeight - rect.bottom - gap;
    const spaceAbove = rect.top - gap;
    const openUp = spaceBelow < Math.min( cap, 240 ) && spaceAbove > spaceBelow;
    const maxHeight = Math.max( 160, Math.min( cap, openUp ? spaceAbove : spaceBelow ) );

    const style = {
        position: 'fixed',
        left: `${ Math.round( rect.left ) }px`,
        width: `${ Math.round( rect.width ) }px`,
        maxHeight: `${ Math.round( maxHeight ) }px`,
        zIndex: 100000,
    };

    if ( openUp ) {
        style.bottom = `${ Math.round( viewportHeight - rect.top + gap ) }px`;
    } else {
        style.top = `${ Math.round( rect.bottom + gap ) }px`;
    }

    dropdownStyle.value = style;
}

function startTracking() {
    window.addEventListener( 'scroll', updatePosition, true );
    window.addEventListener( 'resize', updatePosition );
}

function stopTracking() {
    window.removeEventListener( 'scroll', updatePosition, true );
    window.removeEventListener( 'resize', updatePosition );
}

onMounted( () => document.addEventListener( 'mousedown', handleOutsideClick ) );

onBeforeUnmount( () => {
    document.removeEventListener( 'mousedown', handleOutsideClick );
    stopTracking();
} );
</script>

<template>
    <div ref="rootEl" class="relative h-full w-full md:min-w-[330px]">
        <button
            :id="buttonId"
            :name="name"
            type="button"
            class="flex h-full w-full min-w-0 items-center justify-between gap-3 rounded-[8px] border border-slate-200 bg-white px-4 py-3 text-left text-[14px] text-slate-700 outline-none transition focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
            :class="disabled ? 'cursor-not-allowed bg-slate-50 text-slate-400' : 'cursor-pointer hover:border-slate-300'"
            :aria-expanded="isOpen"
            aria-haspopup="listbox"
            :aria-controls="listboxId"
            :disabled="disabled"
            @click="toggle"
            @keydown.down.prevent="openAndFocus( 0 )"
            @keydown.up.prevent="openAndFocus( options.length - 1 )"
            @keydown.enter.prevent="toggle"
            @keydown.esc.prevent="close"
        >
            <span class="min-w-0 flex-1 truncate" :class="selectedOption ? 'text-slate-800' : 'text-slate-400'">
                {{ selectedLabel }}
            </span>

            <svg
                class="h-4 w-4 shrink-0 text-slate-400 transition duration-150"
                :class="isOpen ? 'rotate-180' : ''"
                viewBox="0 0 20 20"
                fill="none"
                aria-hidden="true"
            >
                <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>

        <!--
            The wrapper — not the dropdown — carries `hubgo-app`, because Tailwind's
            scoping (`important: '.hubgo-app'`) compiles every utility to a
            DESCENDANT selector: utilities never apply to the element holding the
            class itself. With the class on the dropdown its transition utilities
            were inert, so Vue's leave transition never completed and the dropdown
            stayed in the DOM, visible, forever. `display: contents` keeps the
            wrapper out of the layout entirely.
        -->
        <!--
            A named transition backed by plain CSS (see main.css), not Tailwind
            utility classes. Vue's enter-from and leave-to sets shared the same
            utility tokens, so its add/remove bookkeeping left BOTH `opacity-0`
            and `opacity-100` on the node across cycles: the later rule won, the
            leave never resolved, and the dropdown stayed in the DOM and visible.
        -->
        <Teleport to="body">
            <div class="hubgo-app" style="display: contents">
                <transition name="hubgo-select-pop">
                    <div
                        v-if="isOpen"
                        :id="listboxId"
                        ref="dropdownEl"
                        class="hubgo-select-dropdown fixed flex flex-col overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-[0_20px_45px_rgba(15,23,42,0.12)]"
                        :style="dropdownStyle"
                    >
                    <div v-if="showSearch" class="shrink-0 border-b border-slate-100 p-2">
                        <input
                            ref="searchEl"
                            v-model="query"
                            type="text"
                            class="w-full rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-2 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:bg-white focus:ring-4 focus:ring-primary-100"
                            :placeholder="field.search_placeholder || __( 'Pesquisar...' )"
                        >
                    </div>

                    <ul
                        role="listbox"
                        :aria-labelledby="buttonId"
                        class="m-0 min-h-0 flex-1 list-none overflow-auto p-2"
                        @keydown.down.prevent="moveActive( 1 )"
                        @keydown.up.prevent="moveActive( -1 )"
                        @keydown.enter.prevent="commitActive"
                        @keydown.esc.prevent="close"
                    >
                        <li v-if="showEmpty" class="rounded-[8px] px-3 py-2 text-[14px] text-slate-400">
                            {{ field.empty_label || __( 'Nenhuma opção disponível' ) }}
                        </li>

                        <li
                            v-for="( option, index ) in filteredOptions"
                            :key="String( option.value )"
                            role="option"
                            :aria-selected="isSelected( option )"
                            class="flex cursor-pointer items-center justify-between gap-3 rounded-[8px] px-3 py-2 text-[14px] transition"
                            :class="optionClass( index, option )"
                            @mouseenter="activeIndex = index"
                            @click="selectOption( option )"
                        >
                            <div class="min-w-0">
                                <div class="truncate font-medium" :class="isSelected( option ) ? 'text-primary-700' : 'text-slate-700'">
                                    {{ option.label }}
                                </div>
                                <div v-if="option.meta" class="truncate text-[12px] leading-5 text-slate-400">
                                    {{ option.meta }}
                                </div>
                            </div>

                            <svg
                                v-if="isSelected( option )"
                                class="h-4 w-4 shrink-0 text-primary-700"
                                viewBox="0 0 20 20"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path d="M4.5 10.5L8 14L15.5 6.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </li>
                        </ul>
                    </div>
                </transition>
            </div>
        </Teleport>
    </div>
</template>
