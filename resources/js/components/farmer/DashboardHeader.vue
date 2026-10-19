<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/composables/useInitials';
import { edit } from '@/routes/profile';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const name = computed(() => user.value?.name?.trim() || 'Petani IC');
</script>

<template>
    <header class="py-1">
        <div class="flex items-center justify-between gap-3">
            <img
                src="/images/logo-dashboard.png"
                alt="Farm IC"
                class="h-7 w-auto min-w-0 object-contain"
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

                <Link
                    :href="edit()"
                    aria-label="Buka profil"
                    class="rounded-full transition-opacity hover:opacity-80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
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
                </Link>
            </div>
        </div>
    </header>
</template>
