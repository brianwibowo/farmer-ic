<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ChevronDown,
    LoaderCircle,
    Mail,
    Save,
    UserPlus,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CancelInvitationModal from '@/components/CancelInvitationModal.vue';
import DeleteTeamModal from '@/components/DeleteTeamModal.vue';
import InputError from '@/components/InputError.vue';
import InviteMemberModal from '@/components/InviteMemberModal.vue';
import RemoveMemberModal from '@/components/RemoveMemberModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/composables/useInitials';
import { edit, index, update } from '@/routes/teams';
import { update as updateMember } from '@/routes/teams/members';
import type {
    RoleOption,
    Team,
    TeamInvitation,
    TeamMember,
    TeamPermissions,
} from '@/types';

type Props = {
    team: Team;
    members: TeamMember[];
    invitations: TeamInvitation[];
    permissions: TeamPermissions;
    availableRoles: RoleOption[];
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { team: Team }) => ({
        breadcrumbs: [
            {
                title: 'Tim',
                href: index(),
            },
            {
                title: props.team.name,
                href: edit(props.team.slug),
            },
        ],
    }),
});

const { getInitials } = useInitials();

const inviteDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const removeMemberDialogOpen = ref(false);
const memberToRemove = ref<TeamMember | null>(null);
const cancelInvitationDialogOpen = ref(false);
const invitationToCancel = ref<TeamInvitation | null>(null);

function memberRouteArgs(memberId: string): Parameters<typeof updateMember>[0] {
    return {
        team: props.team.slug,
        user: memberId,
    } as unknown as Parameters<typeof updateMember>[0];
}

const pageTitle = computed(() =>
    props.permissions.canUpdateTeam
        ? `Ubah ${props.team.name}`
        : `Lihat ${props.team.name}`,
);

const updateMemberRole = (member: TeamMember, newRole: string) => {
    router.visit(updateMember(memberRouteArgs(member.id)), {
        data: { role: newRole },
        preserveScroll: true,
    });
};

const confirmRemoveMember = (member: TeamMember) => {
    memberToRemove.value = member;
    removeMemberDialogOpen.value = true;
};

const confirmCancelInvitation = (invitation: TeamInvitation) => {
    invitationToCancel.value = invitation;
    cancelInvitationDialogOpen.value = true;
};
</script>

<template>
    <Head :title="pageTitle" />

    <h1 class="sr-only">{{ pageTitle }}</h1>

    <!-- Team settings -->
    <div class="rounded-xl border border-border bg-card shadow-sm">
        <div class="border-b border-border px-5 py-4 sm:px-6">
            <h2 class="admin-section-title">
                {{ permissions.canUpdateTeam ? 'Pengaturan tim' : team.name }}
            </h2>
            <p
                v-if="permissions.canUpdateTeam"
                class="mt-1 text-sm text-muted-foreground"
            >
                Perbarui nama tim Anda.
            </p>
        </div>

        <Form
            v-if="permissions.canUpdateTeam"
            v-bind="update.form(team.slug)"
            class="space-y-5 px-5 py-5 sm:px-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-1.5">
                <Label for="name">Nama tim</Label>
                <Input
                    id="name"
                    name="name"
                    class="admin-field"
                    data-test="team-name-input"
                    :default-value="team.name"
                    required
                />
                <InputError :message="errors.name" />
            </div>

            <div class="flex items-center gap-3 pt-1">
                <Button
                    type="submit"
                    data-test="team-save-button"
                    :disabled="processing"
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

    <!-- Members -->
    <div class="rounded-xl border border-border bg-card shadow-sm">
        <div
            class="flex flex-col gap-3 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
        >
            <div class="min-w-0">
                <h2 class="admin-section-title">Anggota tim</h2>
                <p
                    v-if="permissions.canCreateInvitation"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Kelola siapa saja yang tergabung dalam tim ini.
                </p>
            </div>

            <Button
                v-if="permissions.canCreateInvitation"
                data-test="invite-member-button"
                class="shrink-0"
                @click="inviteDialogOpen = true"
            >
                <UserPlus class="size-4" /> Undang anggota
            </Button>
        </div>

        <ul class="divide-y divide-border">
            <li
                v-for="member in members"
                :key="member.id"
                data-test="member-row"
                class="flex items-center justify-between gap-4 px-5 py-4 sm:px-6"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <Avatar class="size-10 shrink-0">
                        <AvatarImage
                            v-if="member.avatar"
                            :src="member.avatar"
                            :alt="member.name"
                        />
                        <AvatarFallback>{{
                            getInitials(member.name)
                        }}</AvatarFallback>
                    </Avatar>
                    <div class="min-w-0">
                        <div class="truncate font-semibold text-foreground">
                            {{ member.name }}
                        </div>
                        <div class="truncate text-sm text-muted-foreground">
                            {{ member.email }}
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <DropdownMenu
                        v-if="
                            member.role !== 'owner' &&
                            permissions.canUpdateMember
                        "
                    >
                        <DropdownMenuTrigger as-child>
                            <Button
                                data-test="member-role-trigger"
                                variant="outline"
                                size="sm"
                            >
                                {{ member.role_label }}
                                <ChevronDown class="ml-1 size-4 opacity-60" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="admin-dashboard-theme">
                            <DropdownMenuItem
                                v-for="role in availableRoles"
                                :key="role.value"
                                data-test="member-role-option"
                                @click="updateMemberRole(member, role.value)"
                            >
                                {{ role.label }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Badge v-else variant="secondary">
                        {{ member.role_label }}
                    </Badge>

                    <TooltipProvider
                        v-if="
                            member.role !== 'owner' &&
                            permissions.canRemoveMember
                        "
                    >
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="member-remove-button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Keluarkan anggota"
                                    @click="confirmRemoveMember(member)"
                                >
                                    <X class="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Keluarkan anggota</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </li>
        </ul>
    </div>

    <!-- Pending invitations -->
    <div
        v-if="invitations.length > 0"
        class="rounded-xl border border-border bg-card shadow-sm"
    >
        <div class="border-b border-border px-5 py-4 sm:px-6">
            <h2 class="admin-section-title">Undangan tertunda</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Undangan yang belum diterima.
            </p>
        </div>

        <ul class="divide-y divide-border">
            <li
                v-for="invitation in invitations"
                :key="invitation.code"
                data-test="invitation-row"
                class="flex items-center justify-between gap-4 px-5 py-4 sm:px-6"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted"
                    >
                        <Mail
                            class="size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <div class="min-w-0">
                        <div class="truncate font-semibold text-foreground">
                            {{ invitation.email }}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ invitation.role_label }}
                        </div>
                    </div>
                </div>

                <TooltipProvider v-if="permissions.canCancelInvitation">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                data-test="invitation-cancel-button"
                                variant="ghost"
                                size="icon"
                                aria-label="Batalkan undangan"
                                @click="confirmCancelInvitation(invitation)"
                            >
                                <X class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Batalkan undangan</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </li>
        </ul>
    </div>

    <!-- Danger zone -->
    <div
        v-if="permissions.canDeleteTeam && !team.isPersonal"
        class="rounded-xl border border-border bg-card shadow-sm"
    >
        <div class="border-b border-border px-5 py-4 sm:px-6">
            <h2 class="admin-section-title">Hapus tim</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Hapus tim ini secara permanen.
            </p>
        </div>

        <div class="px-5 py-5 sm:px-6">
            <div
                class="flex items-start gap-3 rounded-lg border border-error/20 bg-error/5 p-4"
            >
                <AlertTriangle
                    class="mt-0.5 size-5 shrink-0 text-error"
                    aria-hidden="true"
                />
                <div class="min-w-0 space-y-1 text-sm">
                    <p class="font-medium text-error">Peringatan</p>
                    <p class="text-muted-foreground">
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>
            </div>

            <div class="mt-4">
                <Button
                    data-test="delete-team-button"
                    variant="destructive"
                    @click="deleteDialogOpen = true"
                >
                    Hapus tim
                </Button>
            </div>
        </div>
    </div>

    <InviteMemberModal
        v-if="permissions.canCreateInvitation"
        :team="team"
        :available-roles="availableRoles"
        :open="inviteDialogOpen"
        @update:open="inviteDialogOpen = $event"
    />

    <RemoveMemberModal
        :team="team"
        :member="memberToRemove"
        :open="removeMemberDialogOpen"
        @update:open="removeMemberDialogOpen = $event"
    />

    <CancelInvitationModal
        :team="team"
        :invitation="invitationToCancel"
        :open="cancelInvitationDialogOpen"
        @update:open="cancelInvitationDialogOpen = $event"
    />

    <DeleteTeamModal
        v-if="permissions.canDeleteTeam && !team.isPersonal"
        :team="team"
        :open="deleteDialogOpen"
        @update:open="deleteDialogOpen = $event"
    />
</template>
