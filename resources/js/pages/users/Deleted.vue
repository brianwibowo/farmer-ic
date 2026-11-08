<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Archive, RotateCcw } from '@lucide/vue';
import UserDirectoryTabs from '@/components/users/UserDirectoryTabs.vue';
import UserIdentity from '@/components/users/UserIdentity.vue';
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
    <Head title="Pengguna terhapus" />
    <section
        class="user-directory rounded-xl bg-card"
        aria-labelledby="deleted-users-title"
    >
        <header class="user-directory-heading">
            <div>
                <h1
                    id="deleted-users-title"
                    class="admin-page-title tracking-tight text-primary"
                >
                    Pengguna terhapus
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    Pulihkan akun beserta akses dan penugasan yang masih
                    tersimpan.
                </p>
            </div>
            <span
                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-accent/60 text-primary"
                aria-hidden="true"
                ><Archive class="size-5"
            /></span>
        </header>
        <div class="user-directory-inset">
            <UserDirectoryTabs
                active="deleted"
                :deleted-count="users.total"
                show-deleted
            />
        </div>
        <div v-if="!users.data.length" class="px-5 py-16 text-center">
            <span
                class="mx-auto flex size-14 items-center justify-center rounded-full bg-accent text-primary"
                ><Archive class="size-6"
            /></span>
            <h2 class="mt-4 text-base font-semibold">
                Tidak ada pengguna terhapus
            </h2>
            <p
                class="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground"
            >
                Akun yang dihapus dan dapat Anda pulihkan akan muncul di sini.
            </p>
            <Button as-child variant="outline" class="mt-5"
                ><Link href="/admin/user-management"
                    >Kembali ke pengguna</Link
                ></Button
            >
        </div>
        <template v-else>
            <div
                class="user-directory-inset hidden overflow-x-auto pt-4 md:block"
                role="region"
                aria-label="Tabel pengguna terhapus"
                tabindex="0"
            >
                <table
                    class="user-directory-table w-full min-w-[700px] text-left text-sm"
                >
                    <thead>
                        <tr>
                            <th scope="col">Pengguna</th>
                            <th scope="col">Role</th>
                            <th scope="col">Tim</th>
                            <th scope="col">Tanggal dihapus</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users.data" :key="user.id">
                            <td>
                                <UserIdentity
                                    :name="user.name"
                                    :email="user.email"
                                />
                            </td>
                            <td>
                                <span
                                    class="rounded-full bg-muted px-2.5 py-1.5 text-xs font-semibold text-muted-foreground"
                                    >{{ roleLabel(user.role) }}</span
                                >
                            </td>
                            <td class="text-xs">
                                {{
                                    user.teams
                                        .map((team) => team.name)
                                        .join(', ') || 'Tidak ada tim'
                                }}
                            </td>
                            <td class="text-xs text-muted-foreground">
                                <time :datetime="user.deleted_at">{{
                                    userDate(user.deleted_at)
                                }}</time>
                            </td>
                            <td>
                                <Button
                                    v-if="user.can_restore"
                                    variant="outline"
                                    size="sm"
                                    :aria-label="'Pulihkan ' + user.name"
                                    @click="
                                        selected = user;
                                        form.clearErrors();
                                    "
                                    ><RotateCcw
                                        class="size-4"
                                    />Pulihkan</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ul
                class="user-directory-inset space-y-3 py-4 md:hidden"
                aria-label="Daftar pengguna terhapus"
            >
                <li
                    v-for="user in users.data"
                    :key="user.id"
                    class="space-y-4 rounded-xl bg-background p-4"
                >
                    <UserIdentity :name="user.name" :email="user.email" />
                    <p class="text-xs leading-5 text-muted-foreground">
                        {{ roleLabel(user.role) }} ·
                        {{
                            user.teams.map((team) => team.name).join(', ') ||
                            'Tidak ada tim'
                        }}
                    </p>
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3"
                    >
                        <p class="text-xs text-muted-foreground">
                            Dihapus {{ userDate(user.deleted_at) }}
                        </p>
                        <Button
                            v-if="user.can_restore"
                            variant="outline"
                            size="sm"
                            :aria-label="'Pulihkan ' + user.name"
                            @click="
                                selected = user;
                                form.clearErrors();
                            "
                            ><RotateCcw class="size-4" />Pulihkan</Button
                        >
                    </div>
                </li>
            </ul>
        </template>
        <footer class="user-directory-footer">
            <p class="text-xs text-muted-foreground">
                {{ users.total }} pengguna terhapus dalam cakupan akses Anda
            </p>
            <nav
                v-if="users.last_page > 1"
                class="flex items-center gap-3"
                aria-label="Pagination pengguna terhapus"
            >
                <Button
                    v-if="users.prev_page_url"
                    as-child
                    variant="outline"
                    size="sm"
                    ><Link :href="users.prev_page_url">Sebelumnya</Link></Button
                >
                <span
                    aria-current="page"
                    class="text-xs font-semibold tabular-nums"
                    >{{ users.current_page }} / {{ users.last_page }}</span
                >
                <Button
                    v-if="users.next_page_url"
                    as-child
                    variant="outline"
                    size="sm"
                    ><Link :href="users.next_page_url">Berikutnya</Link></Button
                >
            </nav>
        </footer>
    </section>
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
