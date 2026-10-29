<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Info, LoaderCircle, Save, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import UserAssignments from '@/components/users/UserAssignments.vue';
import UserField from '@/components/users/UserField.vue';
import type {
    ManagedUser,
    UserOptions,
    UserManagementScope,
} from '@/types/user-management';

const props = defineProps<{
    managedUser: ManagedUser | null;
    options: UserOptions;
    isSelf: boolean;
    can: { configure: boolean; changeRole: boolean };
    userManagementScope: UserManagementScope;
}>();
const form = useForm({
    name: props.managedUser?.name ?? '',
    email: props.managedUser?.email ?? '',
    password: '',
    password_confirmation: '',
    role: props.managedUser?.role ?? 'petugas',
    team_ids: [
        ...(props.managedUser?.team_ids ??
            (props.userManagementScope.team
                ? [props.userManagementScope.team.id]
                : [])),
    ],
    assigned_farm_id: props.managedUser?.assigned_farm_id ?? '',
});
const backUrl = props.managedUser
    ? '/admin/user-management/' + props.managedUser.id
    : '/admin/user-management';
const teamError = computed(
    () =>
        Object.entries(form.errors).find(
            ([key]) => key === 'team_ids' || key.startsWith('team_ids.'),
        )?.[1],
);
const controlClass = 'admin-field';

function submit(): void {
    form.clearErrors();
    if (form.password !== form.password_confirmation) {
        form.setError(
            'password_confirmation',
            'Konfirmasi password tidak sama.',
        );
        return;
    }
    if (
        !['owner', 'superadmin'].includes(form.role) &&
        form.team_ids.length > 1
    ) {
        form.setError(
            'team_ids',
            'Admin dan petugas hanya dapat memiliki satu tenant.',
        );
        return;
    }
    const options = {
        preserveScroll: true,
        onSuccess: () => form.reset('password', 'password_confirmation'),
    };
    if (props.managedUser)
        form.put('/admin/user-management/' + props.managedUser.id, options);
    else form.post('/admin/user-management', options);
}
</script>

<template>
    <Head :title="managedUser ? 'Ubah user' : 'Tambah user'" />
    <div class="mx-auto max-w-4xl space-y-6">
        <Link
            :href="backUrl"
            class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"
            ><ArrowLeft class="size-4" />Kembali</Link
        >
        <header>
            <p
                class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase"
            >
                User Management
            </p>
            <h1 class="admin-page-title">
                {{ managedUser ? 'Ubah user' : 'Tambah user baru' }}
            </h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                {{
                    userManagementScope.global
                        ? 'Kelola identitas, role aplikasi, dan asosiasi tenant dalam satu tempat.'
                        : 'Kelola identitas akun dan penugasan farm petugas di tenant Anda.'
                }}
            </p>
        </header>
        <form
            :aria-busy="form.processing"
            class="space-y-6"
            @submit.prevent="submit"
        >
            <div class="space-y-5">
                <section
                    class="space-y-5 rounded-xl border border-border bg-card p-5 md:p-6"
                    aria-labelledby="profile-heading"
                >
                    <h2
                        id="profile-heading"
                        class="admin-section-title flex items-center gap-2"
                    >
                        <UserRound class="size-5 text-primary" />Data akun
                    </h2>
                    <UserField
                        id="user-name"
                        label="Nama lengkap"
                        :error="form.errors.name"
                        required
                    >
                        <template #default="{ id, invalid, description }"
                            ><input
                                :id="id"
                                v-model="form.name"
                                :class="controlClass"
                                type="text"
                                autocomplete="name"
                                maxlength="150"
                                required
                                :aria-invalid="invalid"
                                :aria-describedby="description"
                                placeholder="Nama lengkap user"
                        /></template>
                    </UserField>
                    <UserField
                        id="user-email"
                        label="Email"
                        :error="form.errors.email"
                        required
                    >
                        <template #default="{ id, invalid, description }"
                            ><input
                                :id="id"
                                v-model="form.email"
                                :class="controlClass"
                                type="email"
                                autocomplete="email"
                                maxlength="255"
                                required
                                :aria-invalid="invalid"
                                :aria-describedby="description"
                                placeholder="nama@perusahaan.com"
                        /></template>
                    </UserField>
                    <UserField
                        id="user-role"
                        label="Role aplikasi"
                        :error="form.errors.role"
                        :hint="
                            isSelf
                                ? 'Role akun yang sedang digunakan tidak dapat diubah.'
                                : !can.changeRole
                                  ? 'Role akun yang sudah ada hanya dapat diubah oleh Superadmin.'
                                  : 'Role menentukan izin akses di aplikasi.'
                        "
                        required
                    >
                        <template #default="{ id, invalid, description }"
                            ><select
                                :id="id"
                                v-model="form.role"
                                :class="controlClass"
                                :disabled="isSelf || !can.changeRole"
                                required
                                :aria-invalid="invalid"
                                :aria-describedby="description"
                            >
                                <option
                                    v-for="role in options.roles"
                                    :key="role.value"
                                    :value="role.value"
                                >
                                    {{ role.label }}
                                </option>
                            </select></template
                        >
                    </UserField>
                    <div class="space-y-5 border-t border-border pt-5">
                        <UserField
                            id="user-password"
                            :label="
                                managedUser
                                    ? 'Password baru (opsional)'
                                    : 'Password awal'
                            "
                            :error="form.errors.password"
                            :required="!managedUser"
                            :hint="
                                managedUser
                                    ? 'Kosongkan untuk mempertahankan password saat ini. Mengganti password user lain akan mengakhiri sesi login mereka.'
                                    : 'Minimal 8 karakter. Sampaikan kredensial kepada user melalui kanal yang sesuai.'
                            "
                        >
                            <template #default="{ id, invalid, description }"
                                ><input
                                    :id="id"
                                    v-model="form.password"
                                    :class="controlClass"
                                    type="password"
                                    autocomplete="new-password"
                                    minlength="8"
                                    :required="!managedUser"
                                    :aria-invalid="invalid"
                                    :aria-describedby="description"
                            /></template>
                        </UserField>
                        <UserField
                            id="user-password-confirmation"
                            label="Konfirmasi password"
                            :error="form.errors.password_confirmation"
                            :required="!managedUser || !!form.password"
                        >
                            <template #default="{ id, invalid, description }"
                                ><input
                                    :id="id"
                                    v-model="form.password_confirmation"
                                    :class="controlClass"
                                    type="password"
                                    autocomplete="new-password"
                                    :required="!managedUser || !!form.password"
                                    :aria-invalid="invalid"
                                    :aria-describedby="description"
                            /></template>
                        </UserField>
                    </div>
                </section>
                <div class="space-y-4">
                    <UserAssignments
                        v-model:team-ids="form.team_ids"
                        v-model:farm-id="form.assigned_farm_id"
                        :role="form.role"
                        :options="options"
                        :scope="userManagementScope"
                        :can-configure="can.configure"
                        :team-error="teamError"
                        :farm-error="form.errors.assigned_farm_id"
                    />
                    <p
                        v-if="userManagementScope.global"
                        class="flex gap-2 rounded-xl bg-muted p-4 text-xs leading-6 text-muted-foreground"
                    >
                        <Info class="mt-1 size-4 shrink-0" />Owner dan admin
                        melihat farm melalui asosiasi tenant. Assignment farm
                        individual hanya digunakan oleh petugas. User tanpa
                        assignment belum mendapat akses operasional tenant.
                    </p>
                </div>
            </div>
            <footer
                class="flex flex-wrap justify-end gap-3 rounded-xl border border-border bg-card p-4"
            >
                <Button
                    variant="outline"
                    class="rounded-xl"
                    as-child
                    :disabled="form.processing"
                    ><Link
                        :href="backUrl"
                        :aria-disabled="form.processing"
                        :tabindex="form.processing ? -1 : undefined"
                        :class="{ 'pointer-events-none': form.processing }"
                        @click="form.processing && $event.preventDefault()"
                        >Batal</Link
                    ></Button
                >
                <Button
                    type="submit"
                    class="h-10 rounded-xl"
                    :disabled="form.processing"
                    ><LoaderCircle
                        v-if="form.processing"
                        class="animate-spin"
                    /><Save v-else />{{
                        form.processing ? 'Menyimpan…' : 'Simpan user'
                    }}</Button
                >
            </footer>
        </form>
    </div>
</template>
