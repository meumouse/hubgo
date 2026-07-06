<script setup>
defineProps({
    toasts: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="pointer-events-none fixed bottom-6 right-6 z-[100000] flex flex-col gap-2">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            :class="[
                'pointer-events-auto min-w-[260px] max-w-sm rounded-lg px-4 py-3 text-sm text-white shadow-lg transition-opacity',
                toast.tone === 'success' ? 'bg-emerald-600' : toast.tone === 'danger' ? 'bg-rose-600' : 'bg-slate-800',
                toast.closing ? 'opacity-0' : 'opacity-100',
            ]"
        >
            <p class="font-semibold">{{ toast.title }}</p>
            <p class="mt-0.5 opacity-90">{{ toast.message }}</p>
        </div>
    </div>
</template>
