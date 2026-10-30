<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    LandPlot,
    Pencil,
    ShieldCheck,
    Trash2,
    UserRound,
} from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import DeleteUserDialog from '@/components/users/DeleteUserDialog.vue';
import {
    roleLabel,
    userDate,
    type ManagedUser,
    type UserManagementScope,
} from '@/types/user-management';

defineOptions({ inheritAttrs: false });

defineProps<{
    managedUser: ManagedUser;
    can: { update: boolean; delete: boolean };
    userManagementScope: UserManagementScope;
}>();
const deleting = ref(false);
</script>

<template>
    <Head :title="managedUser.name" />
    <div class="space-y-6">
        <Link
            href="/admin/user-management"
            class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"
            ><ArrowLeft class="size-4" />Kembali ke daftar user</Link
        >
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-4">
                <div
                    class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-accent text-primary"
                >
                    <UserRound class="size-7" />
                </div>
                <div class="min-w-0">
                    <h1 class="admin-page-title tracking-tight break-words">
                        {{ managedUser.name }}
                    </h1>
                    <p class="mt-2 text-sm break-all text-muted-foreground">
                        {{ managedUser.email }}
                    </p>
                    <span
                        class="mt-3 inline-flex rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground"
                        >{{ roleLabel(managedUser.role) }}</span
                    >
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="can.update" as-child class="h-10 rounded-xl"
                    ><Link
                        :href="
                            '/admin/user-management/' + managedUser.id + '/edit'
                        "
                        ><Pencil />Ubah user</Link
                    ></Button
                ><Button
                    v-if="can.delete"
                    variant="destructive-outline"
                    class="h-10 rounded-xl"
                    @click="deleting = true"
                    ><Trash2 />Hapus</Button
                >
            </div>
        </header>
        <div class="grid items-start gap-5 lg:grid-cols-3">
            <section
                class="space-y-5 rounded-xl border border-border bg-card p-5 md:p-6 lg:col-span-2"
            >
                <h2 class="admin-section-title flex items-center gap-2">
                    <ShieldCheck class="size-5 text-primary" />Informasi akun
                </h2>
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Role aplikasi
                        </dt>
                        <dd class="mt-1 font-medium">
                            {{ roleLabel(managedUser.role) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Dibuat</dt>
                        <dd class="mt-1">
                            {{ userDate(managedUser.created_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Terakhir diperbarui
                        </dt>
                        <dd class="mt-1">
                            {{ userDate(managedUser.updated_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">ID user</dt>
                        <dd
                            class="mt-1 font-mono text-xs break-all text-muted-foreground"
                        >
                            {{ managedUser.id }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="managedUser.role === 'superadmin'"
                    class="rounded-xl bg-muted p-3 text-xs leading-6 text-muted-foreground"
                >
                    Akun developer memiliki akses global tanpa memerlukan
                    membership tenant.
                </p>
            </section>
            <section
                class="space-y-5 rounded-xl border border-border bg-card p-5 md:p-6"
            >
                <h2 class="admin-section-title flex items-center gap-2">
                    <Building2 class="size-5 text-primary" />Tenant & farm
                </h2>
                <ul v-if="managedUser.teams.length" class="space-y-2">
                    <li v-for="team in managedUser.teams" :key="team.id">
                        <Link
                            v-if="userManagementScope.global"
                            :href="'/settings/teams/' + team.slug"
                            class="flex items-center gap-3 rounded-xl border border-border p-3 text-sm font-medium hover:bg-muted/50"
                            ><Building2
                                class="size-4 shrink-0 text-muted-foreground"
                            /><span class="break-words">{{
                                team.name
                            }}</span></Link
                        >
                        <div
                            v-else
                            class="flex items-center gap-3 rounded-xl border border-border p-3 text-sm font-medium"
                        >
                            <Building2
                                class="size-4 shrink-0 text-muted-foreground"
                            /><span>{{ team.name }}</span>
                        </div>
                    </li>
                </ul>
                <p
                    v-else
                    class="rounded-xl bg-muted p-4 text-sm text-muted-foreground"
                >
                    {{
                        managedUser.role === 'superadmin'
                            ? 'Assignment tenant tidak diperlukan.'
                            : 'User belum diasosiasikan dengan tenant.'
                    }}
                </p>
                <div
                    v-if="managedUser.role === 'petugas'"
                    class="border-t border-border pt-4"
                >
                    <h3
                        class="mb-3 flex items-center gap-2 text-xs font-medium text-muted-foreground"
                    >
                        <LandPlot class="size-4" />Farm yang ditugaskan
                    </h3>
                    <Link
                        v-if="
                            managedUser.assigned_farm &&
                            userManagementScope.global
                        "
                        :href="
                            '/admin/farm-management/' +
                            managedUser.assigned_farm.id
                        "
                        class="text-sm font-semibold text-primary hover:underline"
                        >{{ managedUser.assigned_farm.name }}</Link
                    >
                    <p
                        v-else-if="managedUser.assigned_farm"
                        class="text-sm font-medium"
                    >
                        {{ managedUser.assigned_farm.name }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Belum ditugaskan ke farm.
                    </p>
                </div>
                <p class="text-xs leading-6 text-muted-foreground">
                    Asosiasi ini menentukan batas akses data. Role dan izin
                    tetap mengikuti matriks RBAC aplikasi.
                </p>
            </section>
        </div>
    </div>
    <DeleteUserDialog
        :user="deleting ? managedUser : null"
        @close="deleting = false"
    />
</template>
