export type PetugasReportSummary = {
    id: string;
    name: string;
    date: string;
    location: string;
    source: 'SOP' | 'Mandiri';
    worker_count: number | null;
    notes: string | null;
    photo: string | null;
    photo_count: number;
};
