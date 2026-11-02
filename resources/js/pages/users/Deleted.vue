<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, RotateCcw } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    roleLabel,
    userDate,
    type ManagedUser,
    type UserPage,
} from '@/types/user-management';

defineOptions({ inheritAttrs: false });

type DeletedUser = ManagedUser & { deleted_at: string; can_restore: boolean };
defineProps<{ users: Omit<UserPage, 'data'> & { data: DeletedUser[] } }>();
const selected = ref<DeletedUser | null>(null);
const form = useForm({});

function restore(): void {
    if (!selected.value) return;
    form.patch('/admin/user-management/' + selected.value.id + '/restore', {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
        },
    });
}
</script>

<template>
    <Head title="User terhapus" />
    <div class="space-y-6">
        <Link
            href="/admin/user-management"
            class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"
        >
            <ArrowLeft class="size-4" />Kembali ke daftar user
        </Link>
        <header>
            <h1 class="admin-page-title tracking-tight">User terhapus</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Pulihkan akun yang telah dihapus beserta akses dan penugasannya
                yang masih tersimpan.
            </p>
        </header>
        <section
            class="overflow-hidden rounded-xl border border-border bg-card"
            aria-label="Daftar user terhapus"
        >
            <p
                v-if="users.data.length === 0"
                class="p-6 text-sm text-muted-foreground"
            >
                Tidak ada user terhapus yang dapat dipulihkan.
            </p>
            <ul v-else class="divide-y divide-border">
                <li
                    v-for="user in users.data"
                    :key="user.id"
                    class="flex flex-wrap items-center justify-between gap-4 p-5"
                >
                    <div class="min-w-0">
                        <p class="font-semibold break-words">{{ user.name }}</p>
                        <p class="text-sm break-all text-muted-foreground">
                            {{ user.email }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ roleLabel(user.role) }} ·
                            {{
                                user.teams
                                    .map((team) => team.name)
                                    .join(', ') || 'Tidak ada tenant'
                            }}
                            · Dihapus {{ userDate(user.deleted_at) }}
                        </p>
                    </div>
                    <Button
                        v-if="user.can_restore"
                        variant="outline"
                        @click="
                            selected = user;
                            form.clearErrors();
                        "
                        ><RotateCcw />Pulihkan</Button
                    >
                </li>
            </ul>
            <nav
                v-if="users.last_page > 1"
                class="flex items-center justify-between gap-3 border-t border-border p-4"
                aria-label="Pagination user terhapus"
            >
                <Button v-if="users.prev_page_url" as-child variant="outline"
                    ><Link :href="users.prev_page_url">Sebelumnya</Link></Button
                ><span v-else />
                <span class="text-sm text-muted-foreground"
                    >{{ users.current_page }} / {{ users.last_page }}</span
                >
                <Button v-if="users.next_page_url" as-child variant="outline"
                    ><Link :href="users.next_page_url">Berikutnya</Link></Button
                ><span v-else />
            </nav>
        </section>
    </div>
    <Dialog
        :open="!!selected"
        @update:open="
            (open) => {
                if (!open && !form.processing) selected = null;
            }
        "
    >
        <DialogContent
            class="admin-dashboard-theme bg-card text-foreground"
            :show-close-button="!form.processing"
        >
            <DialogTitle>Pulihkan user?</DialogTitle>
            <DialogDescription
                >Akun {{ selected?.name }} dapat login kembali dan memperoleh
                akses sesuai role serta tenant yang masih
                tersimpan.</DialogDescription
            >
            <p
                v-if="Object.keys(form.errors).length"
                role="alert"
                class="text-sm text-error"
            >
                {{ Object.values(form.errors)[0] }}
            </p>
            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="form.processing"
                    @click="selected = null"
                    >Batal</Button
                >
                <Button :disabled="form.processing" @click="restore">{{
                    form.processing ? 'Memulihkan…' : 'Pulihkan user'
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
