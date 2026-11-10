<script setup lang="ts">
import { Pencil, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import FarmLegalityDialog from '@/components/farms/FarmLegalityDialog.vue';
import LegalityDocumentList from '@/components/farms/LegalityDocumentList.vue';
import { farmLegalityStatuses, legalityDate } from '@/types/farm-legality';
import type {
    FarmLegalityDetail,
    FarmLegalityLimits,
    FarmLegalityPermissions,
    FarmLegalityUrls,
} from '@/types/farm-legality';
import type { LandTenure } from '@/types/farms';

const props = defineProps<{
    farm: { id: string; name: string; land_tenure: LandTenure | null };
    legality: FarmLegalityDetail | null;
    permissions: FarmLegalityPermissions;
    urls: FarmLegalityUrls;
    limits: FarmLegalityLimits;
}>();
const editing = ref(false);
const canOpen = computed(() =>
    props.legality
        ? props.permissions.update || props.permissions.delete
        : props.permissions.create,
);
const status = computed(() =>
    props.legality ? farmLegalityStatuses[props.legality.status] : null,
);
</script>

<template>
    <section
        class="admin-card min-w-0 bg-card p-4 sm:p-5"
        aria-labelledby="farm-legality-heading"
    >
        <header class="flex items-center justify-between gap-3">
            <h2
                id="farm-legality-heading"
                class="admin-section-title flex items-center gap-2 text-primary"
            >
                <ShieldCheck
                    class="size-5 shrink-0"
                    aria-hidden="true"
                />Legalitas
            </h2>
            <Button
                v-if="canOpen"
                type="button"
                variant="ghost"
                size="icon"
                class="size-11 shrink-0"
                :aria-label="legality ? 'Ubah legalitas' : 'Tambah legalitas'"
                :title="legality ? 'Ubah legalitas' : 'Tambah legalitas'"
                @click="editing = true"
                ><Pencil class="size-4" aria-hidden="true"
            /></Button>
        </header>
        <template v-if="legality">
            <span
                v-if="status"
                class="mt-3 inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold"
                :class="status.style"
                >{{ status.label }}</span
            >
            <dl class="mt-4 grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Tanggal mulai PKS
                    </dt>
                    <dd class="mt-1 text-sm font-semibold">
                        {{
                            legality.status === 'not_applicable'
                                ? 'Tidak berlaku'
                                : legalityDate(legality.pks_started_at)
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Tanggal berakhir PKS
                    </dt>
                    <dd class="mt-1 text-sm font-semibold">
                        {{
                            legality.status === 'not_applicable'
                                ? 'Tidak berlaku'
                                : legalityDate(legality.pks_ends_at)
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Instansi terkait
                    </dt>
                    <dd
                        class="mt-1 text-sm font-semibold [overflow-wrap:anywhere]"
                    >
                        {{ legality.institution }}
                    </dd>
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-xs text-muted-foreground">
                        Ringkasan legalitas
                    </dt>
                    <dd
                        class="mt-1 text-sm leading-6 [overflow-wrap:anywhere] whitespace-pre-line"
                    >
                        {{ legality.summary }}
                    </dd>
                </div>
            </dl>
            <div class="mt-5 border-t border-border pt-4">
                <h3 class="text-sm font-semibold">
                    Draft legalitas
                    <span class="font-normal text-muted-foreground"
                        >({{ legality.documents.length }})</span
                    >
                </h3>
                <LegalityDocumentList
                    class="mt-3"
                    :documents="legality.documents"
                />
            </div>
        </template>
        <div
            v-else
            class="mt-4 rounded-xl border border-dashed border-border bg-muted/20 px-4 py-5"
        >
            <p class="text-sm font-medium">Detail legalitas belum diisi.</p>
            <p
                v-if="permissions.create"
                class="mt-1 text-xs leading-5 text-muted-foreground"
            >
                Gunakan tombol pensil untuk mengisi periode PKS, instansi,
                ringkasan, dan draft PDF.
            </p>
        </div>
    </section>
    <FarmLegalityDialog
        v-model:open="editing"
        :farm="farm"
        :legality="legality"
        :permissions="permissions"
        :urls="urls"
        :limits="limits"
    />
</template>
