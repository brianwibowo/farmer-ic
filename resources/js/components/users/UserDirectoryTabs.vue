<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Archive, Users } from '@lucide/vue';

defineProps<{
    active: 'users' | 'deleted';
    userCount?: number;
    deletedCount?: number;
    showDeleted?: boolean;
}>();
</script>

<template>
    <nav class="user-directory-tabs" aria-label="Daftar pengguna">
        <Link
            href="/admin/user-management"
            class="user-directory-tab"
            :class="{ 'is-active': active === 'users' }"
            :aria-current="active === 'users' ? 'page' : undefined"
        >
            <Users class="size-4" aria-hidden="true" />Pengguna
            <span
                v-if="userCount !== undefined"
                class="user-directory-tab-count"
                >{{ userCount }}</span
            >
        </Link>
        <Link
            v-if="showDeleted"
            href="/admin/user-management/deleted"
            class="user-directory-tab"
            :class="{ 'is-active': active === 'deleted' }"
            :aria-current="active === 'deleted' ? 'page' : undefined"
        >
            <Archive class="size-4" aria-hidden="true" />Terhapus
            <span
                v-if="deletedCount !== undefined"
                class="user-directory-tab-count"
                >{{ deletedCount }}</span
            >
        </Link>
    </nav>
</template>
