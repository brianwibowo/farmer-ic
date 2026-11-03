<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, LogOut, Pencil, Plus, Users } from '@lucide/vue';
import { ref } from 'vue';
import CreateTeamModal from '@/components/CreateTeamModal.vue';
import LeaveTeamModal from '@/components/LeaveTeamModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit, index } from '@/routes/teams';
import type { Team } from '@/types';

type Props = {
    teams: Team[];
    canCreateTeam: boolean;
    canManageAllTeams: boolean;
};

defineProps<Props>();

const leaveTeamDialogOpen = ref(false);
const teamLeaving = ref<Team | null>(null);

const canLeaveTeam = (team: Team) => !team.isPersonal && team.role != null && team.role !== 'owner';

const openLeaveTeamDialog = (team: Team) => {
    teamLeaving.value = team;
    leaveTeamDialogOpen.value = true;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tim',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Tim" />

    <h1 class="sr-only">Tim</h1>

    <div class="rounded-xl border border-border bg-card shadow-sm">
        <div
            class="flex flex-col gap-3 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
        >
            <div class="min-w-0">
                <h2 class="admin-section-title">Tim</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        canManageAllTeams
                            ? 'Kelola seluruh tim dan keanggotaannya.'
                            : 'Kelola tim dan keanggotaan Anda.'
                    }}
                </p>
            </div>

            <CreateTeamModal v-if="canCreateTeam">
                <Button data-test="teams-new-team-button" class="shrink-0">
                    <Plus class="size-4" /> Tenant baru
                </Button>
            </CreateTeamModal>
        </div>

        <ul v-if="teams.length > 0" class="divide-y divide-border">
            <li
                v-for="team in teams"
                :key="team.id"
                data-test="team-row"
                class="flex items-center justify-between gap-4 px-5 py-4 sm:px-6"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <Users class="size-5" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate font-semibold text-foreground">{{
                                team.name
                            }}</span>
                            <Badge v-if="team.isPersonal" variant="secondary">
                                Personal
                            </Badge>
                        </div>
                        <span class="text-sm text-muted-foreground">
                            {{
                                team.roleLabel ??
                                (canManageAllTeams ? 'Administrator global' : '')
                            }}
                        </span>
                    </div>
                </div>

                <TooltipProvider>
                    <div class="flex shrink-0 items-center gap-1">
                        <Tooltip v-if="canLeaveTeam(team)">
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="team-leave-button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Keluar dari tim"
                                    @click="openLeaveTeamDialog(team)"
                                >
                                    <LogOut class="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Keluar dari tim</p>
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip v-if="team.canOpen && !team.canUpdate">
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="team-view-button"
                                    variant="ghost"
                                    size="icon"
                                    as-child
                                >
                                    <Link
                                        :href="edit(team.slug)"
                                        aria-label="Lihat tim"
                                    >
                                        <Eye class="size-4" />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Lihat tim</p>
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip v-else-if="team.canUpdate">
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="team-edit-button"
                                    variant="ghost"
                                    size="icon"
                                    as-child
                                >
                                    <Link
                                        :href="edit(team.slug)"
                                        aria-label="Ubah tim"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Ubah tim</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>
            </li>
        </ul>

        <div
            v-else
            class="flex flex-col items-center gap-2 px-5 py-10 text-center sm:px-6"
        >
            <Users class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="text-sm text-muted-foreground">
                {{
                    canManageAllTeams
                        ? 'Belum ada tim yang dibuat.'
                        : 'Belum ada tim yang dapat Anda lihat dengan akses saat ini.'
                }}
            </p>
        </div>
    </div>

    <LeaveTeamModal v-model:open="leaveTeamDialogOpen" :team="teamLeaving" />
</template>
