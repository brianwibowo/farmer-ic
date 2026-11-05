<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LoaderCircle, LogOut, Settings } from '@lucide/vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/composables/useInitials';
import { useLogout } from '@/composables/useLogout';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import { edit as editProfile } from '@/routes/profile';

withDefaults(
    defineProps<{
        dashboardTheme?: boolean;
        showBrand?: boolean;
    }>(),
    {
        dashboardTheme: false,
        showBrand: true,
    },
);

const page = usePage();
const user = computed(() => page.props.account ?? page.props.auth?.user);
const name = computed(() => user.value?.name?.trim() || 'Petani IC');
const { signOut, processing, error } = useLogout();
</script>

<template>
    <header class="py-1">
        <div class="flex items-center justify-between gap-3">
            <div v-if="$slots.default" class="min-w-0 flex-1"><slot /></div>
            <Link
                v-else-if="showBrand && dashboardTheme"
                href="/petugas"
                aria-label="Beranda Farm IC"
                class="flex min-w-0 items-center gap-2 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                ><img
                    src="/images/logo.png"
                    alt=""
                    class="size-9 shrink-0 object-contain dark:brightness-0 dark:invert"
                /><span
                    class="text-lg font-semibold tracking-tight whitespace-nowrap"
                    >Farm <span class="text-primary">IC</span></span
                ></Link
            >
            <img
                v-else-if="showBrand"
                src="/images/logo-dashboard.png"
                alt="Farm IC"
                class="h-9 w-auto min-w-0 object-contain dark:brightness-0 dark:invert"
            />

            <div class="flex shrink-0 items-center gap-2">
                <NotificationBell />

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            aria-label="Buka profil"
                            class="shrink-0 rounded-full transition-opacity hover:opacity-80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                        >
                            <Avatar class="size-11 rounded-full">
                                <AvatarImage
                                    v-if="user?.avatar"
                                    :src="user.avatar"
                                    :alt="name"
                                />
                                <AvatarFallback
                                    class="bg-primary/15 text-sm font-semibold text-secondary dark:text-primary"
                                >
                                    {{ getInitials(name) }}
                                </AvatarFallback>
                            </Avatar>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        :side-offset="8"
                        class="w-64 max-w-[calc(100vw-2rem)] rounded-xl p-2"
                        :class="{ 'admin-dashboard-theme': dashboardTheme }"
                    >
                        <DropdownMenuLabel>Profil</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <div class="space-y-1 px-3 py-3">
                            <p class="text-sm font-semibold text-foreground">
                                {{ name }}
                            </p>
                            <p
                                v-if="user?.email"
                                class="text-xs break-all text-muted-foreground"
                            >
                                {{ user.email }}
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                Profil tampilan demo
                            </p>
                        </div>
                        <template v-if="user">
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                as-child
                                class="min-h-11 cursor-pointer rounded-xl"
                            >
                                <Link :href="editProfile()">
                                    <Settings
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    Pengaturan profil
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="min-h-11 cursor-pointer rounded-xl"
                                :disabled="processing"
                                @select.prevent="signOut"
                            >
                                <LoaderCircle
                                    v-if="processing"
                                    class="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                                <LogOut
                                    v-else
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{ processing ? 'Sedang keluar…' : 'Keluar' }}
                            </DropdownMenuItem>
                            <p
                                v-if="error"
                                role="alert"
                                class="px-3 py-2 text-xs leading-5 text-error"
                            >
                                {{ error }}
                            </p>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </header>
</template>
