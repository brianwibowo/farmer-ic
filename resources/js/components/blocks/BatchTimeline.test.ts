import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it } from 'vitest';
import type { BatchStage } from '@/types/blocks';
import BatchTimeline from './BatchTimeline.vue';

const first = '2026-11-07T17:00:00Z';
const last = '2026-11-10T01:00:00Z';
const note =
    'Tanggal tahap berdasarkan aktivitas paling awal dan paling akhir yang dilaporkan.';

function stage(
    code: string,
    status: BatchStage['status'],
    startedAt: string | null,
    completedAt: string | null,
): BatchStage {
    return {
        code,
        position: 1,
        status,
        started_at: startedAt,
        completed_at: completedAt,
    };
}

async function render(stages: BatchStage[]): Promise<string> {
    return (await renderToString(h(BatchTimeline, { stages }))).replace(
        /\s+/g,
        ' ',
    );
}

describe('batch stage timeline', () => {
    it('labels report dates for an in-progress stage in Jakarta time', async () => {
        const html = await render([
            stage('planting', 'in_progress', first, last),
        ]);

        expect(html).toContain('Sekarang');
        expect(html).toContain('aria-current="step"');
        expect(html).toContain('Laporan Pertama 8 Nov 2026');
        expect(html).toContain('Laporan Terakhir 10 Nov 2026');
        expect(html).not.toContain('Mulai 8 Nov 2026');
        expect(html).not.toContain('Selesai 10 Nov 2026');
    });

    it('labels the same report dates as start and end for a completed stage', async () => {
        const html = await render([
            stage('soil_preparation', 'completed', first, last),
        ]);

        expect(html).toContain('Mulai 8 Nov 2026');
        expect(html).toContain('Selesai 10 Nov 2026');
        expect(html).not.toContain('Laporan Pertama 8 Nov 2026');
        expect(html).not.toContain('Laporan Terakhir 10 Nov 2026');
    });

    it('shows one explanation for the timeline and handles stages without reports', async () => {
        const html = await render([
            stage('land_clearing', 'completed', null, null),
            stage('soil_preparation', 'upcoming', null, null),
        ]);

        expect(html.match(new RegExp(note, 'g'))).toHaveLength(1);
        expect(html.indexOf(note)).toBeLessThan(html.indexOf('<ol'));
        expect(html).toContain('Tidak ada laporan pada tahap ini');
        expect(html).toContain('Belum dimulai');
        expect(html).not.toContain('Tanggal belum tersedia');
        expect(html).not.toContain('Laporan Pertama');
        expect(html).not.toContain('Laporan Terakhir');
    });

    it('keeps missing legacy end dates hidden and supports a single report date', async () => {
        const html = await render([
            stage('soil_preparation', 'completed', first, null),
            stage('planting', 'in_progress', last, last),
        ]);

        expect(html).toContain('Mulai 8 Nov 2026');
        expect(html).not.toContain('Selesai 8 Nov 2026');
        expect(html).toContain('Laporan Pertama 10 Nov 2026');
        expect(html).toContain('Laporan Terakhir 10 Nov 2026');
    });

    it('does not invent a last report date for a legacy in-progress stage', async () => {
        const html = await render([
            stage('planting', 'in_progress', first, null),
        ]);

        expect(html).toContain('Laporan Pertama 8 Nov 2026');
        expect(html).not.toContain('Laporan Terakhir');
    });
});
