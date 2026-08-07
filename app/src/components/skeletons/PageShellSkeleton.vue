<script setup>
/**
 * PageShellSkeleton.vue — loading placeholder matching the real page shell
 * (header, tab bar, content panel) so the layout does not jump on hydration.
 *
 * @since 3.0.0
 */
defineProps({
    tabs: { type: Number, default: 2 },
    rows: { type: Number, default: 4 },
});
</script>

<template>
    <div class="w-full">
        <header class="flex items-center gap-3">
            <div class="hubgo-skeleton h-8 w-8 rounded-lg" />
            <div class="flex-1 space-y-3">
                <div class="hubgo-skeleton h-7 w-[18rem] max-w-[70vw] rounded-full" />
                <div class="hubgo-skeleton h-4 w-[34rem] max-w-[70vw] rounded-full" />
            </div>
        </header>

        <div class="mt-10 flex w-fit gap-0.5 rounded-[8px] bg-[#e7edf5] p-0.5">
            <div v-for="index in tabs" :key="index" class="hubgo-skeleton h-[62px] w-[165px] rounded-[8px]" />
        </div>

        <section class="mt-8 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
            <div class="space-y-8 px-10 py-12">
                <div
                    v-for="index in rows"
                    :key="index"
                    class="grid items-center gap-6 lg:grid-cols-[minmax(0,420px)_minmax(0,460px)]"
                >
                    <div class="space-y-2">
                        <div class="hubgo-skeleton h-4 w-52 rounded-full" />
                        <div class="hubgo-skeleton h-3 w-80 max-w-full rounded-full" />
                    </div>
                    <div class="hubgo-skeleton h-12 w-full max-w-[330px] rounded-[8px]" />
                </div>
            </div>
        </section>
    </div>
</template>
