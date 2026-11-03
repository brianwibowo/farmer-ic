<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Save } from '@lucide/vue';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

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
</script>

<template>
    <Head title="Pengaturan profil" />

    <h1 class="sr-only">Pengaturan profil</h1>

    <div
        class="rounded-xl border border-border bg-card shadow-sm"
    >
        <div class="border-b border-border px-5 py-4 sm:px-6">
            <h2 class="admin-section-title">Profil</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Perbarui nama, email, dan nomor WhatsApp Anda.
            </p>
        </div>

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-5 px-5 py-5 sm:px-6"
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
                <p id="phone-help" class="text-xs text-muted-foreground">
                    Opsional. Format +6208…, 08…, atau 628… akan disimpan
                    sebagai 628….
                </p>
                <InputError :message="errors.phone_number" />
            </div>

            <div class="flex items-center gap-3 pt-1">
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
    </div>

    <DeleteUser />
</template>
