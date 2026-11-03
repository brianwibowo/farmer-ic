<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Building2,
    ChevronLeft,
    ChevronRight,
    LandPlot,
    LoaderCircle,
    Mail,
    Plus,
    Search,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import DeleteUserDialog from '@/components/users/DeleteUserDialog.vue';
import UserDirectoryTabs from '@/components/users/UserDirectoryTabs.vue';
import UserIdentity from '@/components/users/UserIdentity.vue';
import UserAccountStatus from '@/components/users/UserAccountStatus.vue';
import UserRowActions from '@/components/users/UserRowActions.vue';
import UserFiltersPopover from '@/components/users/UserFiltersPopover.vue';
import UserSummaryCards from '@/components/users/UserSummaryCards.vue';
import {
    roleLabel,
    userDate,
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
const showFilters = ref(false);
const filters = useForm({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
    team_id: props.filters.team_id ?? '',
    farm_id: props.filters.farm_id ?? '',
    per_page: Number(props.filters.per_page ?? 15),
});
const roleSummary = computed(() => [
    ...(props.userManagementScope.global
        ? [{ role: 'superadmin', count: props.summary.superadmin }]
        : []),
    ...(props.userManagementScope.multiTenant
        ? [{ role: 'owner', count: props.summary.owner }]
        : []),
    { role: 'admin', count: props.summary.admin },
    { role: 'petugas', count: props.summary.petugas },
]);
const appliedFilters = computed(() =>
    [
        {
            key: 'search' as const,
            label: props.filters.search
                ? 'Pencarian: ' + props.filters.search
                : '',
        },
        {
            key: 'role' as const,
            label: props.filters.role
                ? 'Role: ' + roleLabel(props.filters.role)
                : '',
        },
        {
            key: 'team_id' as const,
            label:
                props.userManagementScope.multiTenant && props.filters.team_id
                    ? 'Tim: ' +
                      (props.options.teams.find(
                          (team) =>
                              String(team.id) === String(props.filters.team_id),
                      )?.name ?? 'Tidak tersedia')
                    : '',
        },
        {
            key: 'farm_id' as const,
            label: props.filters.farm_id
                ? 'Kebun: ' +
                  (props.options.farms.find(
                      (farm) => farm.id === props.filters.farm_id,
                  )?.name ?? 'Tidak tersedia')
                : '',
        },
    ].filter((item) => item.label),
);
const hasFilters = computed(() => appliedFilters.value.length > 0);
const filterCount = computed(
    () => appliedFilters.value.filter((item) => item.key !== 'search').length,
);
function apply(): void {
    filters.get('/admin/user-management', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            showFilters.value = false;
        },
        onError: (errors) => {
            if (errors.role || errors.team_id || errors.farm_id)
                showFilters.value = true;
        },
    });
}
function reset(): void {
    filters.search = '';
    filters.role = '';
    filters.team_id = '';
    filters.farm_id = '';
    filters.clearErrors();
    apply();
}
function clearFilter(key: 'search' | 'role' | 'team_id' | 'farm_id'): void {
    filters[key] = '';
    if (key === 'team_id') filters.farm_id = '';
    apply();
}
function teamLabel(user: ManagedUser): string {
    return (
        user.teams.map((team) => team.name).join(', ') ||
        (user.role === 'superadmin' ? 'Akses global' : 'Belum ditugaskan')
    );
}
</script>

<template>
    <Head title="Manajemen Pengguna" />
    <div class="space-y-[18px]" :aria-busy="filters.processing">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1 basis-64">
                <h1
                    id="users-title"
                    class="admin-page-title tracking-tight text-primary"
                >
                    Manajemen Pengguna
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    {{
                        userManagementScope.global
                            ? 'Kelola akun, tim, dan penugasan pengguna dalam satu tempat.'
                            : userManagementScope.multiTenant
                              ? 'Lihat pengguna dan penugasan pada seluruh tim yang Anda akses.'
                              : 'Kelola akun dan penugasan pengguna di ' +
                                userManagementScope.team?.name +
                                '.'
                    }}
                </p>
            </div>
            <Button
                v-if="can.create"
                as-child
                class="ml-auto h-10 rounded-xl px-2! sm:px-4! pointer-coarse:min-h-11"
                aria-label="Tambah pengguna"
                title="Tambah pengguna"
                ><Link href="/admin/user-management/create"
                    ><Plus />Tambah pengguna</Link
                ></Button
            >
        </header>
        <UserSummaryCards :total="summary.total" :roles="roleSummary" />
        <section
            class="user-directory rounded-xl bg-card"
            aria-label="Daftar pengguna"
        >
            <div class="user-directory-inset">
                <UserDirectoryTabs
                    active="users"
                    :user-count="summary.total"
                    :show-deleted="can.viewDeleted"
                />
            </div>
            <div class="user-directory-toolbar">
                <form
                    class="flex min-w-0 flex-1 items-center gap-2"
                    @submit.prevent="apply"
                >
                    <div class="min-w-0 grow">
                        <label for="search-users" class="sr-only"
                            >Cari pengguna</label
                        >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            /><input
                                id="search-users"
                                v-model="filters.search"
                                type="search"
                                class="admin-field pl-10!"
                                placeholder="Cari nama atau email…"
                                maxlength="150"
                                :disabled="filters.processing"
                                :aria-invalid="!!filters.errors.search"
                                :aria-describedby="
                                    filters.errors.search
                                        ? 'users-search-error'
                                        : undefined
                                "
                            />
                        </div>
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        class="size-10 shrink-0 pointer-coarse:min-h-11 pointer-coarse:min-w-11"
                        aria-label="Cari pengguna"
                        title="Cari pengguna"
                        :disabled="filters.processing"
                        ><LoaderCircle
                            v-if="filters.processing"
                            class="animate-spin motion-reduce:animate-none" /><Search
                            v-else
                            class="size-4"
                    /></Button>
                </form>
                <div class="flex shrink-0 items-center gap-2">
                    <UserFiltersPopover
                        v-model:open="showFilters"
                        v-model:role="filters.role"
                        v-model:team-id="filters.team_id"
                        v-model:farm-id="filters.farm_id"
                        :options="options"
                        :scope="userManagementScope"
                        :processing="filters.processing"
                        :count="filterCount"
                        :errors="filters.errors"
                        @apply="apply"
                        @reset="reset"
                    />
                </div>
            </div>
            <div
                v-if="hasFilters"
                class="user-directory-inset mb-4 flex flex-wrap items-center gap-2"
                aria-label="Filter yang diterapkan"
            >
                <button
                    v-for="item in appliedFilters"
                    :key="item.key"
                    type="button"
                    class="inline-flex max-w-full items-center gap-2 rounded-md bg-accent/60 px-2.5 py-1.5 text-xs text-accent-foreground hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring"
                    :disabled="filters.processing"
                    :aria-label="'Hapus filter ' + item.label"
                    @click="clearFilter(item.key)"
                >
                    <span class="break-words">{{ item.label }}</span
                    ><X class="size-3.5 shrink-0" />
                </button>
                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="filters.processing"
                    @click="reset"
                    >Reset semua</Button
                >
            </div>
            <p
                v-if="Object.keys(filters.errors).length"
                id="users-search-error"
                role="alert"
                class="user-directory-inset mb-4 text-sm text-error"
            >
                {{ Object.values(filters.errors)[0] }}
            </p>
            <div
                v-if="filters.processing"
                class="user-directory-inset space-y-3 py-5"
                role="status"
                aria-label="Memuat pengguna"
            >
                <div
                    v-for="row in 3"
                    :key="row"
                    class="h-[72px] animate-pulse rounded-xl bg-muted motion-reduce:animate-none"
                />
                <p class="text-sm text-muted-foreground">Memuat pengguna…</p>
            </div>
            <div v-else-if="!users.data.length" class="px-5 py-16 text-center">
                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-full bg-accent text-primary"
                >
                    <Users class="size-6" />
                </div>
                <h2 class="mt-4 text-base font-semibold">
                    {{
                        hasFilters
                            ? 'Pengguna tidak ditemukan'
                            : 'Belum ada pengguna'
                    }}
                </h2>
                <p
                    class="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground"
                >
                    {{
                        hasFilters
                            ? 'Coba kata kunci, role, tim, atau kebun yang berbeda.'
                            : 'Pengguna yang dapat Anda akses akan ditampilkan di sini.'
                    }}
                </p>
                <Button
                    v-if="hasFilters"
                    variant="outline"
                    class="mt-5"
                    @click="reset"
                    >Reset filter</Button
                >
                <Button v-else-if="can.create" as-child class="mt-5"
                    ><Link href="/admin/user-management/create"
                        ><Plus />Tambah pengguna</Link
                    ></Button
                >
            </div>
            <template v-else>
                <div
                    class="user-directory-inset hidden overflow-x-auto md:block"
                    role="region"
                    aria-label="Tabel pengguna"
                    tabindex="0"
                >
                    <table
                        class="user-directory-table w-full min-w-[880px] text-left text-sm"
                    >
                        <thead>
                            <tr>
                                <th scope="col">Pengguna</th>
                                <th scope="col">Email</th>
                                <th scope="col">Role</th>
                                <th scope="col">Tim & kebun tugas</th>
                                <th scope="col">Bergabung</th>
                                <th scope="col">Status akun</th>
                                <th scope="col">
                                    <span class="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users.data" :key="user.id">
                                <td>
                                    <Link
                                        :href="
                                            '/admin/user-management/' + user.id
                                        "
                                        class="block min-w-36 rounded-lg hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
                                        ><UserIdentity :name="user.name"
                                    /></Link>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <Mail
                                            class="size-3.5 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        /><span
                                            class="max-w-52 text-xs break-all"
                                            >{{ user.email }}</span
                                        >
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="inline-flex rounded-full bg-accent/60 px-2.5 py-1.5 text-xs font-semibold whitespace-nowrap text-accent-foreground"
                                        >{{ roleLabel(user.role) }}</span
                                    >
                                </td>
                                <td class="max-w-44">
                                    <p class="text-xs leading-5">
                                        {{ teamLabel(user) }}
                                    </p>
                                    <p
                                        v-if="user.assigned_farm"
                                        class="mt-1 flex items-center gap-1 text-xs leading-5 text-muted-foreground"
                                    >
                                        <LandPlot
                                            class="size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />{{ user.assigned_farm.name }}
                                    </p>
                                </td>
                                <td
                                    class="text-xs whitespace-nowrap text-muted-foreground"
                                >
                                    <time
                                        v-if="user.created_at"
                                        :datetime="user.created_at"
                                        >{{ userDate(user.created_at) }}</time
                                    ><span v-else>Belum tersedia</span>
                                </td>
                                <td>
                                    <UserAccountStatus
                                        :active="user.is_active"
                                    />
                                </td>
                                <td class="text-right">
                                    <UserRowActions
                                        :user="user"
                                        @delete="selectedUser = $event"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul
                    class="user-directory-inset space-y-3 pb-4 md:hidden"
                    aria-label="Daftar pengguna"
                >
                    <li
                        v-for="user in users.data"
                        :key="user.id"
                        class="space-y-4 rounded-xl bg-background p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <Link
                                :href="'/admin/user-management/' + user.id"
                                class="min-w-0 rounded-lg hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
                                ><UserIdentity
                                    :name="user.name"
                                    :email="user.email" /></Link
                            ><UserRowActions
                                :user="user"
                                @delete="selectedUser = $event"
                            />
                        </div>
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span
                                class="rounded-full bg-accent/60 px-2.5 py-1.5 text-xs font-semibold text-accent-foreground"
                                >{{ roleLabel(user.role) }}</span
                            ><UserAccountStatus :active="user.is_active" />
                        </div>
                        <p
                            class="flex items-start gap-2 text-xs leading-5 text-muted-foreground"
                        >
                            <Building2
                                class="size-4 shrink-0"
                                aria-hidden="true"
                            />{{ teamLabel(user) }}
                        </p>
                        <p
                            v-if="user.assigned_farm"
                            class="flex items-start gap-2 text-xs leading-5 text-muted-foreground"
                        >
                            <LandPlot
                                class="size-4 shrink-0"
                                aria-hidden="true"
                            />{{ user.assigned_farm.name }}
                        </p>
                        <p
                            class="border-t border-border pt-3 text-xs text-muted-foreground"
                        >
                            Bergabung {{ userDate(user.created_at) }}
                        </p>
                    </li>
                </ul>
            </template>
            <footer class="user-directory-footer">
                <p class="text-xs text-muted-foreground" role="status">
                    {{
                        users.total
                            ? 'Menampilkan ' +
                              users.from +
                              '–' +
                              users.to +
                              ' dari ' +
                              users.total +
                              ' pengguna'
                            : '0 pengguna ditemukan'
                    }}
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="w-36">
                        <AppSelect
                            id="users-page-size"
                            v-model="filters.per_page"
                            class="admin-field"
                            aria-label="Jumlah pengguna per halaman"
                            :disabled="filters.processing"
                            @change="apply"
                            ><AppSelectOption :value="15"
                                >15 / halaman</AppSelectOption
                            ><AppSelectOption :value="30"
                                >30 / halaman</AppSelectOption
                            ><AppSelectOption :value="50"
                                >50 / halaman</AppSelectOption
                            ></AppSelect
                        >
                    </div>
                    <nav
                        class="flex items-center gap-1"
                        aria-label="Pagination pengguna"
                    >
                        <Button
                            v-if="users.prev_page_url"
                            as-child
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Halaman sebelumnya"
                            ><Link :href="users.prev_page_url" preserve-scroll
                                ><ChevronLeft /></Link></Button
                        ><Button
                            v-else
                            variant="ghost"
                            size="icon-sm"
                            disabled
                            aria-label="Halaman sebelumnya"
                            ><ChevronLeft
                        /></Button>
                        <span
                            aria-current="page"
                            class="px-2 text-xs font-semibold tabular-nums"
                            >{{ users.current_page }} /
                            {{ users.last_page }}</span
                        >
                        <Button
                            v-if="users.next_page_url"
                            as-child
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Halaman berikutnya"
                            ><Link :href="users.next_page_url" preserve-scroll
                                ><ChevronRight /></Link></Button
                        ><Button
                            v-else
                            variant="ghost"
                            size="icon-sm"
                            disabled
                            aria-label="Halaman berikutnya"
                            ><ChevronRight
                        /></Button>
                    </nav>
                </div>
            </footer>
        </section>
    </div>
    <DeleteUserDialog :user="selectedUser" @close="selectedUser = null" />
</template>
