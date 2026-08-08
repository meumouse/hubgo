<script setup>
/**
 * IntegrationMigrationBlock.vue — data migration panel inside an integration modal.
 *
 * Renders the status payload a `migration` block carries and drives
 * POST hubgo/v1/migrations/run until the backend reports the run as complete.
 * The batching lives on the server; this component only keeps calling and
 * repaints the progress bar, so a store with thousands of orders never depends
 * on a single long request.
 *
 * @since 3.0.0
 */
import { computed, ref, watch } from 'vue';
import { api } from '../../utils/api';
import { __, sprintf } from '../../utils/i18n';
import BaseButton from '../buttons/BaseButton.vue';

// Safety net for the request loop: with the default batch size this covers far
// more orders than any store would migrate in one sitting, and it guarantees a
// backend that stops reporting progress can never spin here forever.
const MAX_BATCHES = 500;

const props = defineProps({
    block: { type: Object, required: true },
});

const emit = defineEmits([ 'toast', 'refresh' ]);

const status = ref( { ...( props.block.migration || {} ) } );
const running = ref( false );
const error = ref( '' );

const total = computed( () => Number( status.value.total ) || 0 );
const processed = computed( () => Math.min( Number( status.value.processed ) || 0, total.value ) );
const pending = computed( () => Math.max( total.value - processed.value, 0 ) );
const completed = computed( () => Boolean( status.value.completed ) && 0 === pending.value );
const percent = computed( () => ( 0 === total.value ? 0 : Math.round( ( processed.value / total.value ) * 100 ) ) );

const buttonLabel = computed( () => {
    if ( running.value ) {
        return __( 'Migrating...' );
    }

    // Once everything is migrated the run is not a repeat: it picks up orders
    // the other plugin has written since, which is what the label must promise.
    return completed.value ? __( 'Check for new orders' ) : __( 'Migrate data now' );
} );

// The backend rebuilds the card (and this block) after a completed run, so the
// panel has to follow the refreshed payload instead of its first snapshot.
watch( () => props.block.migration, ( value ) => {
    if ( value && ! running.value ) {
        status.value = { ...value };
    }
} );

/**
 * Run the migration batch by batch until the backend reports completion.
 *
 * @return {Promise<void>}
 */
async function run() {
    if ( running.value || ! status.value.id ) {
        return;
    }

    running.value = true;
    error.value = '';

    try {
        for ( let batch = 0; batch < MAX_BATCHES; batch += 1 ) {
            const response = await api.post( 'migrations/run', {
                id: status.value.id,
                limit: status.value.batch_size || 0,
            } );

            const next = response.migration || {};
            const stalled = ( Number( next.processed ) || 0 ) <= processed.value;

            status.value = { ...next };

            if ( next.completed ) {
                emit( 'toast', response.message || __( 'Migration completed!' ), 'success' );
                emit( 'refresh' );

                return;
            }

            // No forward movement means the source list is not advancing;
            // stopping here beats hammering the endpoint.
            if ( stalled ) {
                break;
            }
        }

        error.value = __( 'The migration stopped before it finished. Run it again to carry on from where it left off.' );
    } catch ( requestError ) {
        error.value = requestError.message || __( 'Could not run the migration.' );
        emit( 'toast', error.value, 'error' );
    } finally {
        running.value = false;
    }
}
</script>

<template>
    <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-4 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h4 class="m-0 text-[14px] font-semibold text-slate-800">
                    {{ status.title || __( 'Data migration' ) }}
                </h4>

                <p v-if="status.description" class="mb-0 mt-1 text-[13px] leading-5 text-slate-500">
                    {{ status.description }}
                </p>
            </div>

            <BaseButton
                :title="buttonLabel"
                color="primary"
                size="sm"
                :loading="running"
                :disabled="! status.available"
                @click="run"
            />
        </div>

        <div v-if="status.available" class="mt-4">
            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                <div
                    class="h-full rounded-full bg-primary-700 transition-all duration-300"
                    :style="{ width: `${percent}%` }"
                />
            </div>

            <p class="mb-0 mt-2 text-[13px] leading-5 text-slate-600">
                {{ sprintf( __( '%1$d of %2$d order(s) processed.' ), processed, total ) }}

                <span v-if="status.imported_records">
                    {{ sprintf( __( '%1$d code(s) imported.' ), status.imported_records ) }}
                </span>
            </p>
        </div>

        <p v-else class="mb-0 mt-3 text-[13px] leading-5 text-slate-500">
            {{ __( 'No Shipment Tracking data was found on the orders of this store.' ) }}
        </p>

        <p
            v-if="completed"
            class="mb-0 mt-3 rounded-[8px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-[13px] leading-5 text-emerald-800"
        >
            {{ __( 'Every order has been migrated. You can now deactivate the Shipment Tracking plugin.' ) }}
        </p>

        <p
            v-if="error"
            class="mb-0 mt-3 rounded-[8px] border border-rose-200 bg-rose-50 px-3 py-2 text-[13px] leading-5 text-rose-700"
        >
            {{ error }}
        </p>
    </div>
</template>
