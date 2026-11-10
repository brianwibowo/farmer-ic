export type FarmLegalityStatus =
    | 'active'
    | 'upcoming'
    | 'expired'
    | 'not_applicable'
    | 'incomplete';

export type FarmLegalityDocument = {
    id: string;
    original_name: string;
    size_bytes: number;
    download_url: string;
};

export type FarmLegalityDetail = {
    id: string;
    pks_started_at: string | null;
    pks_ends_at: string | null;
    institution: string;
    summary: string;
    status: FarmLegalityStatus;
    documents: FarmLegalityDocument[];
};

export type FarmLegalityPermissions = {
    create: boolean;
    update: boolean;
    delete: boolean;
    upload: boolean;
    removeDocuments: boolean;
};

export type FarmLegalityUrls = {
    store: string;
    update: string | null;
    destroy: string | null;
};

export type FarmLegalityLimits = {
    max_files: number;
    max_file_size_mb: number;
    max_upload_size_mb: number;
};

export const farmLegalityStatuses: Record<
    FarmLegalityStatus,
    { label: string; style: string }
> = {
    active: { label: 'PKS aktif', style: 'bg-success/10 text-success' },
    upcoming: {
        label: 'PKS belum dimulai',
        style: 'bg-muted text-muted-foreground',
    },
    expired: { label: 'PKS berakhir', style: 'bg-warning/15 text-foreground' },
    not_applicable: {
        label: 'Tanpa PKS',
        style: 'bg-muted text-muted-foreground',
    },
    incomplete: {
        label: 'Periode belum lengkap',
        style: 'bg-warning/15 text-foreground',
    },
};

export function legalityDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeZone: 'Asia/Jakarta',
          }).format(new Date(`${value}T00:00:00+07:00`))
        : 'Belum tercatat';
}

export function formatLegalityFileSize(value: number): string {
    const megabytes = value / 1024 / 1024;
    return megabytes >= 1
        ? `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(megabytes)} MB`
        : `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.max(1, value / 1024))} KB`;
}
