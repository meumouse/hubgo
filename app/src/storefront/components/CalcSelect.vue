<script setup>
/**
 * CalcSelect.vue — custom listbox for the storefront calculator.
 *
 * The storefront counterpart of the admin `SelectField`: same interaction
 * model, none of its Tailwind. Utilities are scoped with `important:
 * '.hubgo-app'`, so out here every class has to come from `calculator.css`.
 *
 * The list is teleported to <body> and positioned with fixed coordinates.
 * Rendering it in place would put it inside `.hubgo-calc-modal__body`, which is
 * a scroll container and therefore clips on both axes — a 27-state list would
 * be sliced off at the dialog edge. Teleporting costs the calculator's custom
 * properties, so `bridgeTokens()` copies them across exactly like the modal
 * shell does.
 *
 * @since 3.1.0
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Check, ChevronDown } from '@boxicons/vue';
import { bridgeTokens } from '../tokens';
import { __ } from '../../utils/i18n';

const props = defineProps({
    modelValue: { type: [ String, Number ], default: '' },
    /** Options as `{ value, label, meta }`, the shape PHP already emits. */
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    searchPlaceholder: { type: String, default: '' },
    emptyLabel: { type: String, default: '' },
    ariaLabel: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    /** Widget root the custom properties are read from. */
    tokenSource: { type: [ Object, null ], default: null },
});

const emit = defineEmits([ 'update:modelValue' ]);

const SEARCH_THRESHOLD = 8;

const rootEl = ref( null );
const triggerEl = ref( null );
const dropdownEl = ref( null );
const searchEl = ref( null );
const listEl = ref( null );

const isOpen = ref( false );
const query = ref( '' );
const activeIndex = ref( 0 );
const dropdownStyle = ref( {} );

const uid = `hubgo-calc-select-${ Math.random().toString( 36 ).slice( 2, 10 ) }`;
const listboxId = `${ uid }-listbox`;

const normalizedOptions = computed( () => ( Array.isArray( props.options ) ? props.options : [] ).map( ( option ) => ( {
    value: String( option.value ?? '' ),
    label: String( option.label ?? option.value ?? '' ),
    meta: String( option.meta ?? '' ),
} ) ) );

const showSearch = computed( () => normalizedOptions.value.length > SEARCH_THRESHOLD );

const filteredOptions = computed( () => {
    const needle = query.value.trim().toLowerCase();

    if ( ! needle ) {
        return normalizedOptions.value;
    }

    return normalizedOptions.value.filter( ( option ) =>
        option.label.toLowerCase().includes( needle )
        || option.meta.toLowerCase().includes( needle )
        || option.value.toLowerCase().includes( needle ),
    );
} );

const selectedOption = computed(
    () => normalizedOptions.value.find( ( option ) => option.value === String( props.modelValue ?? '' ) ) || null,
);

const triggerLabel = computed(
    () => selectedOption.value?.label || props.placeholder || __( 'Select an option' ),
);

watch( isOpen, async ( value ) => {
    if ( ! value ) {
        stopTracking();

        return;
    }

    query.value = '';
    activeIndex.value = Math.max( 0, filteredOptions.value.findIndex( isSelected ) );

    await nextTick();

    // The list left the widget root when it was teleported, so it has to be
    // handed the resolved tokens while the enter transition still has it
    // hidden — otherwise it flashes the stylesheet defaults.
    bridgeTokens( props.tokenSource, dropdownEl.value );

    updatePosition();
    startTracking();

    if ( showSearch.value && searchEl.value ) {
        searchEl.value.focus();
    } else if ( listEl.value ) {
        listEl.value.focus();
    }

    scrollActiveIntoView();
} );

watch( filteredOptions, ( value ) => {
    const selectedIndex = value.findIndex( isSelected );
    activeIndex.value = selectedIndex >= 0 ? selectedIndex : 0;
} );

/**
 * Whether an option matches the current value.
 *
 * @param {object} option Normalized option.
 * @return {boolean}
 */
function isSelected( option ) {
    return option.value === String( props.modelValue ?? '' );
}

/**
 * Open or close the list.
 *
 * @return {void}
 */
function toggle() {
    if ( props.disabled ) {
        return;
    }

    if ( isOpen.value ) {
        close( false );

        return;
    }

    openAt( 0 );
}

/**
 * Open the list with a given row highlighted.
 *
 * @param {number} index Row to highlight.
 * @return {void}
 */
function openAt( index ) {
    if ( props.disabled ) {
        return;
    }

    // Anchor before the first paint. `position: fixed` with auto offsets keeps
    // the element at its static position — the bottom of <body>, where it was
    // teleported — so opening and then measuring flashes the list in the wrong
    // place for a frame.
    updatePosition();

    isOpen.value = true;
    activeIndex.value = clampIndex( index );
}

/**
 * Close the list and hand focus back to the trigger.
 *
 * @param {boolean} refocus Whether to move focus back.
 * @return {void}
 */
function close( refocus = true ) {
    if ( ! isOpen.value ) {
        return;
    }

    isOpen.value = false;

    if ( refocus && triggerEl.value ) {
        triggerEl.value.focus();
    }
}

/**
 * Keep an index inside the filtered list.
 *
 * @param {number} index Candidate index.
 * @return {number}
 */
function clampIndex( index ) {
    if ( ! filteredOptions.value.length ) {
        return 0;
    }

    return Math.max( 0, Math.min( index, filteredOptions.value.length - 1 ) );
}

/**
 * Move the highlight, keeping the row visible.
 *
 * @param {number} delta Rows to move by.
 * @return {void}
 */
function moveActive( delta ) {
    if ( ! filteredOptions.value.length ) {
        return;
    }

    activeIndex.value = clampIndex( activeIndex.value + delta );
    scrollActiveIntoView();
}

/**
 * Commit the highlighted row.
 *
 * @return {void}
 */
function commitActive() {
    const option = filteredOptions.value[ activeIndex.value ];

    if ( option ) {
        selectOption( option );
    }
}

/**
 * Emit the picked value and close.
 *
 * @param {object} option Normalized option.
 * @return {void}
 */
function selectOption( option ) {
    emit( 'update:modelValue', option.value );
    close();
}

/**
 * Scroll the highlighted row into view inside the list.
 *
 * @return {void}
 */
function scrollActiveIntoView() {
    nextTick( () => {
        if ( ! listEl.value ) {
            return;
        }

        const row = listEl.value.children[ activeIndex.value ];

        if ( row && typeof row.scrollIntoView === 'function' ) {
            row.scrollIntoView( { block: 'nearest' } );
        }
    } );
}

/**
 * Anchor the teleported list to the trigger, flipping up when the space below
 * runs out — the finder modal is vertically centred, so a list opened near the
 * viewport bottom would otherwise render off-screen.
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
        left: `${ Math.round( rect.left ) }px`,
        // Flush with the trigger, like the settings listbox. The field is sized
        // to fit the longest option, so the list never needs to outgrow it.
        width: `${ Math.round( rect.width ) }px`,
        maxHeight: `${ Math.round( maxHeight ) }px`,
    };

    if ( openUp ) {
        style.bottom = `${ Math.round( viewportHeight - rect.top + gap ) }px`;
    } else {
        style.top = `${ Math.round( rect.bottom + gap ) }px`;
    }

    dropdownStyle.value = style;
}

/**
 * Close when the click lands outside both the trigger and the list.
 *
 * @param {MouseEvent} event Pointer event.
 * @return {void}
 */
function handleOutsideClick( event ) {
    if ( ! isOpen.value ) {
        return;
    }

    const insideRoot = rootEl.value && rootEl.value.contains( event.target );
    const insideDropdown = dropdownEl.value && dropdownEl.value.contains( event.target );

    if ( insideRoot || insideDropdown ) {
        return;
    }

    close( false );

    // The modal scrim closes the dialog on `click`. Pressing it to dismiss an
    // open list would otherwise dismiss the dialog behind it in the same
    // gesture, so that one click is swallowed. Scoped to the scrim: swallowing
    // every outside click would cost a second press on the fields beside us.
    if ( event.target instanceof Element && event.target.closest( '.hubgo-calc-modal__overlay' ) ) {
        document.addEventListener( 'click', swallowClickOnce, true );
    }
}

/**
 * Eat a single capture-phase click, then step aside.
 *
 * @param {MouseEvent} event Pointer event.
 * @return {void}
 */
function swallowClickOnce( event ) {
    event.stopPropagation();
    document.removeEventListener( 'click', swallowClickOnce, true );
}

function startTracking() {
    // Capture: the modal body is itself a scroll container, and its scroll
    // events never reach window in the bubble phase.
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
    document.removeEventListener( 'click', swallowClickOnce, true );
    stopTracking();
} );
</script>

<template>
    <div ref="rootEl" class="hubgo-calc__select">
        <button
            ref="triggerEl"
            type="button"
            class="hubgo-calc__finder-field hubgo-calc__select-trigger"
            :class="{ 'is-open': isOpen, 'is-placeholder': ! selectedOption }"
            :aria-label="ariaLabel"
            :aria-expanded="isOpen"
            :aria-controls="listboxId"
            aria-haspopup="listbox"
            :disabled="disabled"
            @click="toggle"
            @keydown.down.prevent="openAt( 0 )"
            @keydown.up.prevent="openAt( normalizedOptions.length - 1 )"
        >
            <span class="hubgo-calc__select-value">{{ triggerLabel }}</span>

            <ChevronDown class="hubgo-calc__select-caret" aria-hidden="true" />
        </button>

        <Teleport to="body">
            <transition name="hubgo-calc-select-pop">
                <div
                    v-if="isOpen"
                    :id="listboxId"
                    ref="dropdownEl"
                    class="hubgo-calc-select"
                    :style="dropdownStyle"
                    @keydown.down.prevent="moveActive( 1 )"
                    @keydown.up.prevent="moveActive( -1 )"
                    @keydown.enter.prevent="commitActive"
                    @keydown.esc.stop.prevent="close()"
                >
                    <div v-if="showSearch" class="hubgo-calc-select__search">
                        <input
                            ref="searchEl"
                            v-model="query"
                            type="text"
                            :placeholder="searchPlaceholder || __( 'Search…' )"
                            :aria-label="searchPlaceholder || __( 'Search…' )"
                        >
                    </div>

                    <ul
                        ref="listEl"
                        class="hubgo-calc-select__list"
                        role="listbox"
                        tabindex="-1"
                        :aria-label="ariaLabel"
                    >
                        <li v-if="! filteredOptions.length" class="hubgo-calc-select__empty">
                            {{ emptyLabel || __( 'No option available' ) }}
                        </li>

                        <li
                            v-for="( option, index ) in filteredOptions"
                            :key="option.value"
                            class="hubgo-calc-select__option"
                            :class="{ 'is-active': index === activeIndex, 'is-selected': isSelected( option ) }"
                            role="option"
                            :aria-selected="isSelected( option )"
                            @mouseenter="activeIndex = index"
                            @click="selectOption( option )"
                        >
                            <span class="hubgo-calc-select__option-body">
                                <span class="hubgo-calc-select__option-label">{{ option.label }}</span>

                                <span v-if="option.meta" class="hubgo-calc-select__option-meta">{{ option.meta }}</span>
                            </span>

                            <Check v-if="isSelected( option )" class="hubgo-calc-select__check" aria-hidden="true" />
                        </li>
                    </ul>
                </div>
            </transition>
        </Teleport>
    </div>
</template>
