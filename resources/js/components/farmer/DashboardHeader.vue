<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Bell, LoaderCircle, LogOut } from '@lucide/vue';
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

const page = usePage();
const user = computed(() => page.props.account ?? page.props.auth?.user);
const name = computed(() => user.value?.name?.trim() || 'Petani IC');
const { signOut, processing, error } = useLogout();
</script>

<template>
    <header class="py-1">
        <div class="flex items-center justify-between gap-3">
            <img
                src="/images/logo-dashboard.svg"
                alt="Farm IC"
                class="h-9 w-auto min-w-0 object-contain dark:brightness-0 dark:invert"
            />

            <div class="flex shrink-0 items-center gap-2">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            aria-label="Buka notifikasi"
                            class="flex size-11 items-center justify-center rounded-full bg-muted/40 text-primary transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                        >
                            <Bell
                                class="size-5"
                                :stroke-width="1.75"
                                aria-hidden="true"
                            />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        :side-offset="8"
                        class="w-64 max-w-[calc(100vw-2rem)] rounded-xl p-2"
                    >
                        <DropdownMenuLabel>Notifikasi</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <div
                            class="flex flex-col items-center gap-2 px-3 py-5 text-center"
                        >
                            <Bell
                                class="size-6 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <p class="text-sm font-medium">
                                Belum ada notifikasi
                            </p>
                            <p class="text-xs leading-5 text-muted-foreground">
                                Pemberitahuan kebun akan muncul di sini.
                            </p>
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            aria-label="Buka profil"
                            class="rounded-full transition-opacity hover:opacity-80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                        >
                            <Avatar class="size-11 rounded-full">
                                <AvatarImage v-if="user?.avatar" :src="user.avatar" :alt="name" />
                                <AvatarFallback class="bg-primary/15 text-sm font-semibold text-secondary dark:text-primary">
                                    {{ getInitials(name) }}
                                </AvatarFallback>
                            </Avatar>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" :side-offset="8" class="w-64 max-w-[calc(100vw-2rem)] rounded-xl p-2">
                        <DropdownMenuLabel>Profil</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <div class="space-y-1 px-3 py-3">
                            <p class="text-sm font-semibold text-foreground">{{ name }}</p>
                            <p v-if="user?.email" class="break-all text-xs text-muted-foreground">{{ user.email }}</p>
                            <p v-else class="text-xs text-muted-foreground">Profil tampilan demo</p>
                        </div>
                        <template v-if="user">
                            <DropdownMenuSeparator />
                            <DropdownMenuItem class="min-h-11 cursor-pointer rounded-xl" :disabled="processing" @select.prevent="signOut">
                                <LoaderCircle v-if="processing" class="size-4 animate-spin" aria-hidden="true" />
                                <LogOut v-else class="size-4" aria-hidden="true" />
                                {{ processing ? 'Sedang keluar…' : 'Keluar' }}
                            </DropdownMenuItem>
                            <p v-if="error" role="alert" class="px-3 py-2 text-xs leading-5 text-error">{{ error }}</p>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </header>
</template>
