<script setup>
/**
 * IntegrationCard.vue — one integration in the Integrations grid.
 *
 * Renders the brand mark, the reason a card cannot be enabled yet, and the
 * actions available for its current state: install the missing plugin, or open
 * the settings modal.
 *
 * Every flag it reads (`plugin_active`, `can_install`, `disabled_message`) is
 * resolved server-side, so this component stays a pure renderer.
 *
 * @since 3.0.0
 * @version 3.1.0
 */
import { computed } from 'vue';
import { Package } from '@boxicons/vue';
import { __, sprintf } from '../../../utils/i18n';
import ToggleSwitch from '../../../components/toggles/ToggleSwitch.vue';
import BaseButton from '../../../components/buttons/BaseButton.vue';

const props = defineProps({
    card: { type: Object, required: true },
    enabled: { type: Boolean, default: false },
    installing: { type: Boolean, default: false },
});

const emit = defineEmits([ 'toggle', 'configure', 'install' ]);

// Resolved server-side: true for fields *or* modal-only content.
const hasSettings = computed( () => Boolean( props.card.has_settings ) );
const isComingSoon = computed( () => Boolean( props.card.coming_soon ) );
const missingPlugin = computed( () => Boolean( props.card.requires_plugin ) && ! props.card.plugin_active );
const toggleDisabled = computed( () => isComingSoon.value || missingPlugin.value );
const showConfigButton = computed( () => hasSettings.value && props.enabled && ! toggleDisabled.value );
const showInstallButton = computed( () => Boolean( props.card.can_install ) && ! isComingSoon.value );
// Resolved server-side too: declared by the integration, or read from the
// header of the plugin it depends on. Empty when the vendor is unknown.
const authorLabel = computed( () => sprintf( __( 'By %s' ), props.card.author || '' ) );
const configLabel = computed( () => props.card?.modal?.button_label || __( 'Configure' ) );
const installLabel = computed( () => props.card?.install?.label || __( 'Install plugin' ) );

const enabledProxy = computed( {
    get: () => props.enabled,
    set: () => {
        if ( ! toggleDisabled.value ) {
            emit( 'toggle' );
        }
    },
} );
</script>

<template>
    <div class="flex h-full flex-col rounded-[8px] bg-white ring-1 ring-slate-200">
        <div class="flex min-h-[140px] items-center justify-center border-b border-slate-100 px-6 py-8">
            <!--
                The box is 80px tall but up to 200px wide so horizontal
                wordmarks (Melhor Envio, Frenet) keep a readable size. The SVG
                scales with preserveAspectRatio, so square brand marks still
                render at 80x80 — height stays the binding constraint.
            -->
            <div
                v-if="card.icon"
                class="flex h-20 w-full max-w-[200px] items-center justify-center [&_svg]:h-full [&_svg]:w-full"
                v-html="card.icon"
            />
            <component :is="Package" v-else width="48" height="48" class="text-slate-300" aria-hidden="true" />
        </div>

        <div class="flex flex-1 flex-col px-6 py-6 text-center">
            <h3 class="m-0 text-[19px] font-semibold leading-7 text-slate-800">{{ card.title }}</h3>

            <p v-if="card.author" class="mb-0 mt-1 text-[12px] leading-5 text-slate-400">
                <a
                    v-if="card.author_url"
                    class="text-slate-400 no-underline hover:text-slate-600 hover:underline"
                    :href="card.author_url"
                    target="_blank"
                    rel="noreferrer"
                >
                    {{ authorLabel }}
                </a>

                <template v-else>{{ authorLabel }}</template>
            </p>

            <p v-if="card.description" class="mb-0 mt-4 text-[13px] leading-6 text-slate-500">
                {{ card.description }}
            </p>

            <!--
                The three notices are mutually exclusive and the toggle swaps
                between them live, so they are keyed and crossfaded instead of
                being replaced under the pointer.
            -->
            <div class="mt-5 space-y-3 text-left">
                <Transition name="hubgo-fade" mode="out-in">
                    <div
                        v-if="isComingSoon"
                        key="coming-soon"
                        class="rounded-[8px] border border-primary-100 bg-primary-50 px-4 py-3 text-[13px] font-medium text-primary-800"
                    >
                        {{ __( 'Coming soon' ) }}
                    </div>

                    <div
                        v-else-if="card.disabled_message"
                        key="disabled"
                        class="rounded-[8px] border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] leading-5 text-amber-800"
                    >
                        {{ card.disabled_message }}
                    </div>

                    <div
                        v-else-if="hasSettings && ! enabled"
                        key="hint"
                        class="rounded-[8px] border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] leading-5 text-slate-600"
                    >
                        {{ __( 'Enable the integration to access its settings.' ) }}
                    </div>
                </Transition>
            </div>

            <div class="mt-6 flex flex-1 flex-col items-center justify-end gap-4">
                <ToggleSwitch
                    :id="`hubgo-integration-${card.slug}`"
                    :aria-label="sprintf( __( 'Enable %s' ), card.title || '' )"
                    size="md"
                    :disabled="toggleDisabled"
                    v-model="enabledProxy"
                />

                <Transition name="hubgo-fade" mode="out-in">
                    <BaseButton
                        v-if="showInstallButton"
                        key="install"
                        :title="installLabel"
                        color="outline"
                        size="sm"
                        :loading="installing"
                        @click="$emit( 'install', card.slug )"
                    />

                    <BaseButton
                        v-else-if="showConfigButton"
                        key="configure"
                        :title="configLabel"
                        color="outline"
                        size="sm"
                        @click="$emit( 'configure', card.slug )"
                    />
                </Transition>

                <a
                    v-if="card.doc_url"
                    class="text-[13px] font-semibold text-primary-700 no-underline hover:underline"
                    :href="card.doc_url"
                    target="_blank"
                    rel="noreferrer"
                >
                    {{ __( 'Learn more' ) }}
                </a>
            </div>
        </div>
    </div>
</template>
