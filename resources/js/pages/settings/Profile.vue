<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Save } from '@lucide/vue';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import MobileProfileForm from '@/components/settings/MobileProfileForm.vue';
import LogoutAllDevices from '@/components/settings/LogoutAllDevices.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';

const props = defineProps<{
    passwordRules?: string;
    canLogoutAllDevices: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pengaturan profil',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const presentation = computed(() => {
    const value = page.props.profilePresentation as
        | { surface?: string; returnUrl?: string }
        | undefined;

    return {
        isMobile: value?.surface === 'mobile',
        returnUrl: value?.returnUrl ?? '/',
    };
});
const profile = computed(
    () =>
        page.props.profile as {
            name: string;
            email: string;
            phone_number: string | null;
            avatar: string | null;
        },
);

const breadcrumbs = [{ title: 'Pengaturan profil', href: edit() }];
</script>

<template>
    <Head title="Pengaturan profil" />

    <FarmerMobileShell v-if="presentation.isMobile" :show-navigation="false">
        <MobileProfileForm
            :return-url="presentation.returnUrl"
            :profile="profile"
            :can-logout-all-devices="props.canLogoutAllDevices"
        />
    </FarmerMobileShell>

    <AppLayout v-else :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <div class="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-2">
                <section
                    class="flex h-full flex-col rounded-xl border border-border bg-card shadow-sm"
                >
                    <div class="border-b border-border px-5 py-4 sm:px-6">
                        <h2 class="admin-section-title">Profil</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Perbarui nama, email, dan nomor WhatsApp Anda.
                        </p>
                    </div>

                    <Form
                        v-bind="ProfileController.update.form()"
                        class="flex flex-1 flex-col gap-5 px-5 py-5 sm:px-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-1.5">
                            <Label for="name">Nama lengkap</Label>
                            <Input
                                id="name"
                                class="admin-field"
                                name="name"
                                :default-value="user.name"
                                required
                                autocomplete="name"
                                placeholder="Masukkan nama lengkap"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="email">Alamat email</Label>
                            <Input
                                id="email"
                                type="email"
                                class="admin-field"
                                name="email"
                                :default-value="user.email"
                                required
                                autocomplete="username"
                                placeholder="contoh@email.com"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="phone_number">Nomor WhatsApp</Label>
                            <Input
                                id="phone_number"
                                name="phone_number"
                                type="tel"
                                class="admin-field"
                                autocomplete="tel"
                                inputmode="tel"
                                :default-value="user.phone_number ?? ''"
                                placeholder="081234567890"
                                pattern="\+?[0-9][0-9 \(\)\-]{7,23}"
                                maxlength="24"
                                aria-describedby="phone-help"
                            />
                            <p
                                id="phone-help"
                                class="text-xs text-muted-foreground"
                            >
                                Opsional. Format +6208…, 08…, atau 628… akan
                                disimpan sebagai 628….
                            </p>
                            <InputError :message="errors.phone_number" />
                        </div>

                        <div class="mt-auto flex items-center gap-3 pt-1">
                            <Button
                                :disabled="processing"
                                data-test="update-profile-button"
                            >
                                <LoaderCircle
                                    v-if="processing"
                                    class="size-4 animate-spin"
                                />
                                <Save v-else class="size-4" />
                                Simpan
                            </Button>
                        </div>
                    </Form>
                </section>

                <section
                    class="flex h-full flex-col rounded-xl border border-border bg-card shadow-sm"
                >
                    <div class="border-b border-border px-5 py-4 sm:px-6">
                        <h2 class="admin-section-title">Ubah kata sandi</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Pastikan akun Anda menggunakan kata sandi yang kuat
                            dan unik.
                        </p>
                    </div>

                    <Form
                        v-bind="SecurityController.update.form()"
                        :options="{
                            preserveScroll: true,
                        }"
                        reset-on-success
                        :reset-on-error="[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]"
                        class="flex flex-1 flex-col gap-5 px-5 py-5 sm:px-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-1.5">
                            <Label for="current_password"
                                >Kata sandi saat ini</Label
                            >
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                class="admin-field"
                                autocomplete="current-password"
                                placeholder="Masukkan kata sandi saat ini"
                            />
                            <InputError :message="errors.current_password" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="password">Kata sandi baru</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                class="admin-field"
                                autocomplete="new-password"
                                placeholder="Masukkan kata sandi baru"
                                :passwordrules="props.passwordRules"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="password_confirmation"
                                >Konfirmasi kata sandi baru</Label
                            >
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                class="admin-field"
                                autocomplete="new-password"
                                placeholder="Ulangi kata sandi baru"
                                :passwordrules="props.passwordRules"
                            />
                            <InputError
                                :message="errors.password_confirmation"
                            />
                        </div>

                        <div class="mt-auto flex items-center gap-3 pt-1">
                            <Button
                                :disabled="processing"
                                data-test="update-password-button"
                            >
                                <LoaderCircle
                                    v-if="processing"
                                    class="size-4 animate-spin"
                                />
                                <Save v-else class="size-4" />
                                Simpan kata sandi
                            </Button>
                        </div>
                    </Form>
                </section>
            </div>
            <LogoutAllDevices v-if="props.canLogoutAllDevices" />
        </SettingsLayout>
    </AppLayout>
</template>
