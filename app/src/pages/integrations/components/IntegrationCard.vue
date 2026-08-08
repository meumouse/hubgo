<script setup>
/**
 * IntegrationCard.vue — one integration in the Integrations grid.
 *
 * Renders the brand mark, the Pro badge for licensed integrations, the reason a
 * card cannot be enabled yet, and the actions available for its current state:
 * install the missing plugin, or open the settings modal.
 *
 * Every flag it reads (`plugin_active`, `is_locked`, `can_install`,
 * `disabled_message`) is resolved server-side, so this component stays a pure
 * renderer.
 *
 * @since 3.0.0
 */
import { computed } from 'vue';
import { Package } from '@boxicons/vue';
import { __, sprintf } from '../../../utils/i18n';
import ToggleSwitch from '../../../components/toggles/ToggleSwitch.vue';
import ProBadge from '../../../components/badges/ProBadge.vue';
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
const isLocked = computed( () => Boolean( props.card.is_locked ) );
const toggleDisabled = computed( () => isComingSoon.value || missingPlugin.value || isLocked.value );
const showConfigButton = computed( () => hasSettings.value && props.enabled && ! toggleDisabled.value );
const showInstallButton = computed( () => Boolean( props.card.can_install ) && ! isComingSoon.value );
const configLabel = computed( () => props.card?.modal?.button_label || __( 'Configurar' ) );
const installLabel = computed( () => props.card?.install?.label || __( 'Instalar plugin' ) );

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
            <div class="flex items-center justify-center gap-2">
                <h3 class="m-0 text-[19px] font-semibold leading-7 text-slate-800">{{ card.title }}</h3>
                <ProBadge v-if="card.requires_license" :locked="isLocked" size="sm" />
            </div>

            <p v-if="card.description" class="mb-0 mt-4 text-[13px] leading-6 text-slate-500">
                {{ card.description }}
            </p>

            <div class="mt-5 space-y-3 text-left">
                <div
                    v-if="isComingSoon"
                    class="rounded-[8px] border border-primary-100 bg-primary-50 px-4 py-3 text-[13px] font-medium text-primary-800"
                >
                    {{ __( 'Em breve' ) }}
                </div>

                <div
                    v-else-if="card.disabled_message"
                    class="rounded-[8px] border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] leading-5 text-amber-800"
                >
                    {{ card.disabled_message }}
                </div>

                <div
                    v-else-if="hasSettings && ! enabled"
                    class="rounded-[8px] border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] leading-5 text-slate-600"
                >
                    {{ __( 'Ative a integração para acessar as configurações.' ) }}
                </div>
            </div>

            <div class="mt-6 flex flex-1 flex-col items-center justify-end gap-4">
                <ToggleSwitch
                    :id="`hubgo-integration-${card.slug}`"
                    :aria-label="sprintf( __( 'Ativar %s' ), card.title || '' )"
                    size="md"
                    :disabled="toggleDisabled"
                    v-model="enabledProxy"
                />

                <BaseButton
                    v-if="showInstallButton"
                    :title="installLabel"
                    color="outline"
                    size="sm"
                    :loading="installing"
                    @click="$emit( 'install', card.slug )"
                />

                <BaseButton
                    v-else-if="showConfigButton"
                    :title="configLabel"
                    color="outline"
                    size="sm"
                    @click="$emit( 'configure', card.slug )"
                />

                <a
                    v-if="card.doc_url"
                    class="text-[13px] font-semibold text-primary-700 no-underline hover:underline"
                    :href="card.doc_url"
                    target="_blank"
                    rel="noreferrer"
                >
                    {{ __( 'Saiba mais' ) }}
                </a>
            </div>
        </div>
    </div>
</template>
