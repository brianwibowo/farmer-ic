<script setup lang="ts">
interface Props {
    name: string;
    value: string;
    image?: string;
}

defineProps<Props>();
</script>

<template>
    <div
        class="flex h-32 w-32 shrink-0 flex-col justify-between rounded-[16px] border border-sidebar-border/70 bg-white p-3 text-center shadow-sm dark:border-sidebar-border dark:bg-sidebar"
    >
        <span class="text-xs font-bold text-gray-700 dark:text-gray-300">
            {{ name }}
        </span>

        <!-- ===================================================================== -->
        <!-- [PERBAIKAN 1] Render Vektor Gambar jika :image ada, atau Placeholder jika tidak ada -->
        <!-- ===================================================================== -->
        <div class="my-auto flex h-12 w-full items-center justify-center rounded-lg p-1 dark:bg-gray-800">
            <img
                v-if="image"
                :src="image"
                :alt="name"
                class="h-10 w-10 object-contain"
                @error="(e) => ((e.target as HTMLElement).style.display = 'none')"
            />
            <span v-else class="text-[10px] text-gray-400">[ {{ name }} ]</span>
        </div>

        <span class="text-xs font-black text-[#1E5E27] dark:text-emerald-400">
            {{ value }}
        </span>
    </div>
</template>