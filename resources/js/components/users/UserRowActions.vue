<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Ellipsis, Pencil, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import type { ManagedUser } from '@/types/user-management';

defineProps<{ user: ManagedUser }>();
defineEmits<{ delete: [user: ManagedUser] }>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="rounded-full"
                :aria-label="'Aksi untuk ' + user.name"
                ><Ellipsis class="size-5"
            /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="admin-dashboard-theme min-w-44 rounded-xl border-border p-1.5"
        >
            <DropdownMenuItem as-child class="min-h-10 rounded-lg">
                <Link :href="'/admin/user-management/' + user.id"
                    ><ArrowUpRight />Lihat detail</Link
                >
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="user.can?.update"
                as-child
                class="min-h-10 rounded-lg"
            >
                <Link :href="'/admin/user-management/' + user.id + '/edit'"
                    ><Pencil />Ubah pengguna</Link
                >
            </DropdownMenuItem>
            <DropdownMenuSeparator v-if="user.can?.delete" />
            <DropdownMenuItem
                v-if="user.can?.delete"
                variant="destructive"
                class="min-h-10 rounded-lg"
                @select="$emit('delete', user)"
                ><Trash2 />Hapus pengguna</DropdownMenuItem
            >
        </DropdownMenuContent>
    </DropdownMenu>
</template>
