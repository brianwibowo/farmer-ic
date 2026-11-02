<script setup lang="ts">
defineProps<{
    errors: Record<string, string | undefined>;
    fields: Record<string, { id: string; label: string }>;
}>();
</script>

<template>
    <div
        v-if="Object.values(errors).some(Boolean)"
        data-form-errors
        tabindex="-1"
        role="alert"
        class="rounded-xl border border-error/40 bg-error/10 p-4 text-sm text-foreground focus-visible:outline-2 focus-visible:outline-ring"
    >
        <p class="font-semibold">Perubahan belum disimpan</p>
        <p class="mt-1">Perbaiki isian berikut, lalu simpan kembali.</p>
        <ul class="mt-3 list-disc space-y-2 pl-5">
            <li v-for="(message, key) in errors" :key="key">
                <a
                    v-if="fields[key]"
                    :href="`#${fields[key].id}`"
                    class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring"
                    >{{ fields[key].label }}: {{ message }}</a
                >
                <span v-else>{{ message }}</span>
            </li>
        </ul>
    </div>
</template>
