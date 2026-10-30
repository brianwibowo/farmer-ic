<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Building2,
    ChevronLeft,
    ChevronRight,
    LoaderCircle,
    Pencil,
    Plus,
    Search,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import DeleteUserDialog from '@/components/users/DeleteUserDialog.vue';
import {
    roleLabel,
    type ManagedUser,
    type UserFilters,
    type UserOptions,
    type UserPage,
    type UserManagementScope,
} from '@/types/user-management';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    users: UserPage;
    summary: {
        total: number;
        superadmin: number;
        owner: number;
        admin: number;
        petugas: number;
    };
    filters: UserFilters;
    options: UserOptions;
    can: { create: boolean; viewDeleted: boolean };
    userManagementScope: UserManagementScope;
}>();
const selectedUser = ref<ManagedUser | null>(null);
const filters = useForm({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
    team_id: props.filters.team_id ?? '',
    farm_id: props.filters.farm_id ?? '',
    per_page: props.filters.per_page ?? 15,
});
const hasFilters = computed(
    () =>
        !!(
            filters.search ||
            filters.role ||
            filters.farm_id ||
            (filters.team_id &&
                (props.userManagementScope.multiTenant ||
                    String(filters.team_id) !==
                        String(props.userManagementScope.team?.id)))
        ),
);
const availableFarms = computed(() =>
    props.options.farms.filter(
        (farm) =>
            !props.userManagementScope.multiTenant ||
            !filters.team_id ||
            String(farm.team_id) === String(filters.team_id),
    ),
);
watch(
    () => filters.team_id,
    () => {
        if (
            filters.farm_id &&
            !availableFarms.value.some((farm) => farm.id === filters.farm_id)
        ) {
            filters.farm_id = '';
        }
    },
);
const metrics = computed(() => [
    props.userManagementScope.global
        ? { label: 'Superadmin', value: props.summary.superadmin, icon: Users }
        : { label: 'Total user', value: props.summary.total, icon: Users },
    ...(props.userManagementScope.multiTenant
        ? [{ label: 'Owner', value: props.summary.owner, icon: Users }]
        : []),
    { label: 'Admin', value: props.summary.admin, icon: Users },
    { label: 'Petugas', value: props.summary.petugas, icon: Users },
]);
const controlClass = 'admin-field';
function apply(): void {
    filters.get('/admin/user-management', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
function reset(): void {
    filters.search = '';
    filters.role = '';
    filters.team_id = '';
    filters.farm_id = '';
    apply();
}
</script>

<template>
    <Head title="User Management" />
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p
                    class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase"
                >
                    Administrasi pengguna
                </p>
                <h1 class="admin-page-title tracking-tight">User Management</h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    {{
                        userManagementScope.global
                            ? 'Kelola akun dan assignment tenant maupun farm untuk seluruh pengguna.'
                            : userManagementScope.multiTenant
                              ? 'Lihat pengguna pada tenant yang terasosiasi dengan akun Anda.'
                              : 'Kelola akun Admin dan Petugas serta assignment farm dalam tenant ' +
                                userManagementScope.team?.name +
                                '.'
                    }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="can.viewDeleted"
                    as-child
                    variant="outline"
                    class="h-10 rounded-xl"
                >
                    <Link href="/admin/user-management/deleted"
                        ><Trash2 />User terhapus</Link
                    >
                </Button>
                <Button v-if="can.create" as-child class="h-10 rounded-xl"
                    ><Link href="/admin/user-management/create"
                        ><Plus />Tambah user</Link
                    ></Button
                >
            </div>
        </header>
        <section
            class="grid gap-4 sm:grid-cols-3"
            :class="userManagementScope.multiTenant ? 'lg:grid-cols-4' : ''"
            aria-label="Ringkasan seluruh user"
        >
            <div
                v-for="metric in metrics"
                :key="metric.label"
                class="rounded-xl border border-border bg-card p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">
                        {{ metric.label }}
                    </p>
                    <component
                        :is="metric.icon"
                        class="size-5 text-primary"
                        aria-hidden="true"
                    />
                </div>
                <p class="admin-metric-value mt-4 tracking-tight">
                    {{ metric.value }}
                    <span class="ml-2 text-lg font-medium text-muted-foreground"
                        >user</span
                    >
                </p>
            </div>
        </section>
        <section
            class="overflow-hidden rounded-xl border border-border bg-card"
            :aria-busy="filters.processing"
        >
            <div class="border-b border-border p-4 md:p-5">
                <div class="mb-4 flex items-center gap-2">
                    <h2 class="admin-section-title">Daftar user</h2>
                    <span
                        class="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground"
                        >{{ users.total }}</span
                    >
                </div>
                <form
                    class="grid items-start gap-3 sm:grid-cols-2"
                    :class="
                        userManagementScope.multiTenant
                            ? 'xl:grid-cols-4'
                            : 'xl:grid-cols-3'
                    "
                    @submit.prevent="apply"
                >
                    <div class="space-y-2">
                        <label
                            for="search-users"
                            class="block text-sm font-medium"
                            >Cari user</label
                        ><input
                            id="search-users"
                            type="search"
                            v-model="filters.search"
                            :class="controlClass"
                            placeholder="Nama atau email…"
                            maxlength="150"
                        />
                    </div>
                    <div class="space-y-2">
                        <label
                            for="filter-role"
                            class="block text-sm font-medium"
                            >Role</label
                        ><select
                            id="filter-role"
                            v-model="filters.role"
                            :class="controlClass"
                        >
                            <option value="">Semua role</option>
                            <option
                                v-for="role in options.roles"
                                :key="role.value"
                                :value="role.value"
                            >
                                {{ role.label }}
                            </option>
                        </select>
                    </div>
                    <div
                        v-if="userManagementScope.multiTenant"
                        class="space-y-2"
                    >
                        <label
                            for="filter-team"
                            class="block text-sm font-medium"
                            >Tenant</label
                        ><select
                            id="filter-team"
                            v-model="filters.team_id"
                            :class="controlClass"
                        >
                            <option value="">Semua tenant</option>
                            <option
                                v-for="team in options.teams"
                                :key="team.id"
                                :value="team.id"
                            >
                                {{ team.name }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label
                            for="filter-farm"
                            class="block text-sm font-medium"
                            >Kebun</label
                        >
                        <select
                            id="filter-farm"
                            v-model="filters.farm_id"
                            :class="controlClass"
                            aria-describedby="filter-farm-hint"
                        >
                            <option value="">Semua kebun</option>
                            <option
                                v-for="farm in availableFarms"
                                :key="farm.id"
                                :value="farm.id"
                            >
                                {{ farm.name
                                }}{{
                                    userManagementScope.multiTenant &&
                                    !filters.team_id
                                        ? ' — ' +
                                          (options.teams.find(
                                              (team) =>
                                                  team.id === farm.team_id,
                                          )?.name ?? '')
                                        : ''
                                }}
                            </option>
                        </select>
                        <p
                            id="filter-farm-hint"
                            class="text-xs text-muted-foreground"
                        ></p>
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-3 sm:col-span-2"
                        :class="
                            userManagementScope.multiTenant
                                ? 'xl:col-span-4'
                                : 'xl:col-span-3'
                        "
                    >
                        <Button
                            type="submit"
                            class="h-10 rounded-xl"
                            :disabled="filters.processing"
                            ><LoaderCircle
                                v-if="filters.processing"
                                class="animate-spin"
                            /><Search v-else />Cari</Button
                        ><Button
                            v-if="hasFilters"
                            type="button"
                            variant="ghost"
                            :disabled="filters.processing"
                            @click="reset"
                            >Reset</Button
                        >
                    </div>
                </form>
            </div>
            <p
                v-if="Object.keys(filters.errors).length"
                role="alert"
                class="p-4 text-sm text-error"
            >
                {{ Object.values(filters.errors)[0] }}
            </p>
            <p
                v-if="filters.processing"
                role="status"
                class="flex items-center gap-2 px-5 py-3 text-sm text-muted-foreground"
            >
                <LoaderCircle class="size-4 animate-spin" />Memuat user…
            </p>
            <div v-if="users.data.length === 0" class="px-5 py-14 text-center">
                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-xl bg-accent text-primary"
                >
                    <Users class="size-7" />
                </div>
                <h2 class="mt-4 text-base font-semibold">
                    {{ hasFilters ? 'User tidak ditemukan' : 'Belum ada user' }}
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{
                        hasFilters
                            ? 'Coba nama, role, tenant, atau kebun yang berbeda.'
                            : 'Tambahkan akun pertama untuk mulai mengatur assignment.'
                    }}
                </p>
                <Button
                    v-if="hasFilters"
                    type="button"
                    variant="outline"
                    class="mt-5"
                    @click="reset"
                    >Reset filter</Button
                >
                <Button v-else-if="can.create" as-child class="mt-5 rounded-xl"
                    ><Link href="/admin/user-management/create"
                        ><Plus />Tambah user pertama</Link
                    ></Button
                >
            </div>
            <template v-else>
                <div
                    class="hidden overflow-x-auto md:block"
                    role="region"
                    aria-label="Tabel user"
                    tabindex="0"
                >
                    <table class="w-full text-left text-sm">
                        <thead
                            class="bg-muted/50 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th scope="col" class="px-5 py-3">User</th>
                                <th scope="col" class="px-5 py-3">Role</th>
                                <th scope="col" class="px-5 py-3">Tenant</th>
                                <th scope="col" class="px-5 py-3">Farm</th>
                                <th scope="col" class="px-5 py-3 text-right">
                                    Tindakan
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="hover:bg-muted/40"
                            >
                                <td class="px-5 py-4">
                                    <Link
                                        :href="
                                            '/admin/user-management/' + user.id
                                        "
                                        class="font-semibold hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
                                        >{{ user.name }}</Link
                                    >
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ user.email }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground"
                                        >{{ roleLabel(user.role) }}</span
                                    >
                                </td>
                                <td class="max-w-xs px-5 py-4">
                                    <p
                                        v-if="user.teams.length"
                                        class="text-xs leading-6"
                                    >
                                        {{
                                            user.teams
                                                .map((team) => team.name)
                                                .join(', ')
                                        }}
                                    </p>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            user.role === 'superadmin'
                                                ? 'Akses global'
                                                : 'Belum ditugaskan'
                                        }}</span
                                    >
                                </td>
                                <td
                                    class="px-5 py-4 text-xs text-muted-foreground"
                                >
                                    {{ user.assigned_farm?.name ?? '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            as-child
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="'Detail ' + user.name"
                                            ><Link
                                                :href="
                                                    '/admin/user-management/' +
                                                    user.id
                                                "
                                                ><ArrowUpRight /></Link></Button
                                        ><Button
                                            v-if="user.can?.update"
                                            as-child
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="'Ubah ' + user.name"
                                            ><Link
                                                :href="
                                                    '/admin/user-management/' +
                                                    user.id +
                                                    '/edit'
                                                "
                                                ><Pencil /></Link></Button
                                        ><Button
                                            v-if="user.can?.delete"
                                            variant="destructive-ghost"
                                            size="icon"
                                            :aria-label="'Hapus ' + user.name"
                                            @click="selectedUser = user"
                                            ><Trash2
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul
                    class="divide-y divide-border md:hidden"
                    aria-label="Daftar user"
                >
                    <li
                        v-for="user in users.data"
                        :key="user.id"
                        class="space-y-3 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <Link
                                :href="'/admin/user-management/' + user.id"
                                class="min-w-0"
                                ><p class="font-semibold break-words">
                                    {{ user.name }}
                                </p>
                                <p
                                    class="mt-1 text-xs break-all text-muted-foreground"
                                >
                                    {{ user.email }}
                                </p></Link
                            ><span
                                class="shrink-0 rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-accent-foreground"
                                >{{ roleLabel(user.role) }}</span
                            >
                        </div>
                        <p
                            class="flex gap-2 text-xs leading-5 text-muted-foreground"
                        >
                            <Building2 class="size-4 shrink-0" />{{
                                user.teams
                                    .map((team) => team.name)
                                    .join(', ') ||
                                (user.role === 'superadmin'
                                    ? 'Akses global'
                                    : 'Belum ditugaskan')
                            }}
                        </p>
                        <p
                            v-if="user.assigned_farm"
                            class="text-xs text-muted-foreground"
                        >
                            Farm: {{ user.assigned_farm.name }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button as-child size="sm" variant="outline"
                                ><Link
                                    :href="'/admin/user-management/' + user.id"
                                    >Detail</Link
                                ></Button
                            ><Button
                                v-if="user.can?.update"
                                as-child
                                size="sm"
                                variant="ghost"
                                ><Link
                                    :href="
                                        '/admin/user-management/' +
                                        user.id +
                                        '/edit'
                                    "
                                    ><Pencil />Ubah</Link
                                ></Button
                            ><Button
                                v-if="user.can?.delete"
                                size="sm"
                                variant="destructive-ghost"
                                @click="selectedUser = user"
                                ><Trash2 />Hapus</Button
                            >
                        </div>
                    </li>
                </ul>
                <nav
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-4"
                    aria-label="Pagination user"
                >
                    <div
                        class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground"
                    >
                        <span
                            >{{ users.from }}–{{ users.to }} dari
                            {{ users.total }} user</span
                        ><label class="flex items-center gap-2"
                            >Per halaman<select
                                v-model="filters.per_page"
                                class="h-8 rounded-lg border border-input bg-background px-2 text-foreground"
                                @change="apply"
                            >
                                <option :value="15">15</option>
                                <option :value="30">30</option>
                                <option :value="50">50</option>
                            </select></label
                        >
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="users.prev_page_url"
                            as-child
                            size="sm"
                            variant="outline"
                            aria-label="Halaman sebelumnya"
                            ><Link :href="users.prev_page_url" preserve-scroll
                                ><ChevronLeft /><span class="hidden sm:inline"
                                    >Sebelumnya</span
                                ></Link
                            ></Button
                        ><Button
                            v-else
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled
                            aria-label="Halaman sebelumnya"
                        >
                            <ChevronLeft /><span class="hidden sm:inline"
                                >Sebelumnya</span
                            > </Button
                        ><span aria-current="page" class="px-1 text-xs"
                            >{{ users.current_page }} /
                            {{ users.last_page }}</span
                        ><Button
                            v-if="users.next_page_url"
                            as-child
                            size="sm"
                            variant="outline"
                            aria-label="Halaman berikutnya"
                            ><Link :href="users.next_page_url" preserve-scroll
                                ><span class="hidden sm:inline">Berikutnya</span
                                ><ChevronRight /></Link
                        ></Button>
                        <Button
                            v-else
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled
                            aria-label="Halaman berikutnya"
                        >
                            <span class="hidden sm:inline">Berikutnya</span
                            ><ChevronRight />
                        </Button>
                    </div>
                </nav>
            </template>
        </section>
    </div>
    <DeleteUserDialog :user="selectedUser" @close="selectedUser = null" />
</template>
