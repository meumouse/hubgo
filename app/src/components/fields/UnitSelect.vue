<script setup>
/**
 * UnitSelect.vue
 *
 * Compact listbox for a CSS length unit, designed to sit inside a control shell
 * next to a numeric input. Like SelectField, the menu is teleported to <body>
 * and positioned with fixed coordinates so it escapes the settings card's
 * `overflow` clipping — but it stays deliberately small: no search, no meta
 * lines, just the handful of units a length can take.
 *
 * @since 3.0.0
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Check, ChevronDown } from '@boxicons/vue';

const props = defineProps({
    modelValue: { type: String, default: 'px' },
    units: { type: Array, default: () => [ 'rem', 'em', 'px', '%' ] },
    disabled: { type: Boolean, default: false },
    ariaLabel: { type: String, default: '' },
});

const emit = defineEmits([ 'update:modelValue', 'open', 'close' ]);

const rootEl = ref( null );
const menuEl = ref( null );
const isOpen = ref( false );
const menuStyle = ref( {} );

const uid = `hubgo-unit-${ Math.random().toString( 36 ).slice( 2, 10 ) }`;

watch( isOpen, ( value ) => {
    if ( ! value ) {
        stopTracking();
        emit( 'close' );

        return;
    }

    emit( 'open' );
    updatePosition();
    startTracking();

    window.requestAnimationFrame( updatePosition );
} );

/**
 * Toggle the menu, unless the control is disabled.
 *
 * @return {void}
 */
function toggle() {
    if ( props.disabled ) {
        return;
    }

    isOpen.value = ! isOpen.value;
}

/**
 * Commit a unit and close the menu.
 *
 * @param {string} unit Unit to select.
 * @return {void}
 */
function selectUnit( unit ) {
    emit( 'update:modelValue', unit );
    isOpen.value = false;
}

/**
 * Anchor the menu to the trigger, flipping upwards when there is no room below.
 *
 * @return {void}
 */
function updatePosition() {
    if ( ! rootEl.value || typeof window === 'undefined' ) {
        return;
    }

    const rect = rootEl.value.getBoundingClientRect();
    const gap = 6;
    const width = Math.max( 96, Math.round( rect.width ) );
    const estimated = props.units.length * 36 + 16;
    const openUp = window.innerHeight - rect.bottom - gap < estimated && rect.top > estimated;

    const style = {
        position: 'fixed',
        // Right-align with the trigger so the menu never overflows the card.
        left: `${ Math.round( rect.right - width ) }px`,
        width: `${ width }px`,
        zIndex: 100000,
    };

    if ( openUp ) {
        style.bottom = `${ Math.round( window.innerHeight - rect.top + gap ) }px`;
    } else {
        style.top = `${ Math.round( rect.bottom + gap ) }px`;
    }

    menuStyle.value = style;
}

function startTracking() {
    window.addEventListener( 'scroll', updatePosition, true );
    window.addEventListener( 'resize', updatePosition );
}

function stopTracking() {
    window.removeEventListener( 'scroll', updatePosition, true );
    window.removeEventListener( 'resize', updatePosition );
}

/**
 * Close when the pointer goes down outside both the trigger and the menu.
 *
 * @param {MouseEvent} event Pointer event.
 * @return {void}
 */
function handleOutsideClick( event ) {
    if ( ! rootEl.value ) {
        return;
    }

    const insideRoot = rootEl.value.contains( event.target );
    const insideMenu = menuEl.value && menuEl.value.contains( event.target );

    if ( ! insideRoot && ! insideMenu ) {
        isOpen.value = false;
    }
}

onMounted( () => document.addEventListener( 'mousedown', handleOutsideClick ) );

onBeforeUnmount( () => {
    document.removeEventListener( 'mousedown', handleOutsideClick );
    stopTracking();
} );
</script>

<template>
    <div ref="rootEl" class="flex shrink-0">
        <button
            :id="`${ uid }-button`"
            type="button"
            class="flex h-full items-center gap-1.5 border-l border-slate-100 px-3 text-[13px] font-medium text-slate-500 transition hover:text-primary-700"
            :class="disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
            :aria-label="ariaLabel || undefined"
            :aria-expanded="isOpen"
            aria-haspopup="listbox"
            :aria-controls="`${ uid }-listbox`"
            :disabled="disabled"
            @click="toggle"
            @keydown.esc.prevent="isOpen = false"
        >
            {{ modelValue }}

            <ChevronDown
                class="h-4 w-4 shrink-0 text-slate-400 transition duration-150"
                :class="isOpen ? 'rotate-180' : ''"
                width="16"
                height="16"
                aria-hidden="true"
            />
        </button>

        <!--
            The wrapper — not the menu — carries `hubgo-app`: Tailwind's scoping
            compiles every utility to a descendant selector, so utilities never
            apply to the element holding the class itself. See SelectField.
        -->
        <Teleport to="body">
            <div class="hubgo-app" style="display: contents">
                <transition name="hubgo-select-pop">
                    <ul
                        v-if="isOpen"
                        :id="`${ uid }-listbox`"
                        ref="menuEl"
                        role="listbox"
                        :aria-labelledby="`${ uid }-button`"
                        class="m-0 list-none overflow-hidden rounded-[10px] border border-slate-200 bg-white p-1.5 shadow-[0_20px_45px_rgba(15,23,42,0.12)]"
                        :style="menuStyle"
                    >
                        <li
                            v-for="unit in units"
                            :key="unit"
                            role="option"
                            :aria-selected="unit === modelValue"
                            class="flex cursor-pointer items-center justify-between rounded-[8px] px-3 py-2 text-[13px] transition"
                            :class="unit === modelValue ? 'bg-primary-50 font-semibold text-primary-700' : 'text-slate-600 hover:bg-slate-50'"
                            @click="selectUnit( unit )"
                        >
                            {{ unit }}

                            <Check
                                v-if="unit === modelValue"
                                class="h-4 w-4 shrink-0"
                                width="16"
                                height="16"
                                aria-hidden="true"
                            />
                        </li>
                    </ul>
                </transition>
            </div>
        </Teleport>
    </div>
</template>
