import { readFileSync, writeFileSync } from 'node:fs';
import { renderToString } from '@vue/server-renderer';
import { h } from 'vue';
import { describe, expect, it } from 'vitest';
import type { PetugasHomeIncident } from '@/types/petugas-home';
import HomeIncidentAlerts from './HomeIncidentAlerts.vue';

const fixtures: PetugasHomeIncident[] = [
    {
        id: 'demo-emergency',
        severity: 'emergency',
        severity_label: 'Darurat',
        category: 'Banjir',
        description: 'Contoh: air meluap ke petak tanam.',
        reported_at: '2026-11-10T01:30:00Z',
        status: 'open',
        action: 'Periksa akses dan saluran drainase.',
    },
    {
        id: 'demo-heavy',
        severity: 'heavy',
        severity_label: 'Berat',
        category: 'Alat rusak',
        description: 'Contoh: pompa irigasi berhenti berfungsi.',
        reported_at: '2026-11-10T01:00:00Z',
        status: 'in_progress',
        action: null,
    },
    {
        id: 'demo-medium',
        severity: 'medium',
        severity_label: 'Sedang',
        category: 'Hama',
        description: 'Contoh: ditemukan serangan ulat pada beberapa baris.',
        reported_at: '2026-11-10T00:30:00Z',
        status: 'open',
        action: null,
    },
    {
        id: 'demo-light',
        severity: 'light',
        severity_label: 'Ringan',
        category: 'Alat rusak',
        description: 'Contoh: sambungan selang irigasi bocor.',
        reported_at: '2026-11-10T00:00:00Z',
        status: 'open',
        action: null,
    },
];
const render = (data: PetugasHomeIncident[], total = data.length) =>
    renderToString(h(HomeIncidentAlerts, { incidents: { data, total } }));

describe('incident home alerts', () => {
    it('announces emergencies and presents all four severity colors and labels', async () => {
        const html = await render(fixtures);
        for (const label of ['Darurat', 'Berat', 'Sedang', 'Ringan'])
            expect(html).toContain(`Insiden · ${label}`);
        expect(html.match(/role="alert"/g)).toHaveLength(1);
        expect(html.match(/role="status"/g)).toHaveLength(3);
        for (const color of [
            'text-error',
            'petugas-incident-heavy',
            'text-warning',
            'text-info',
        ])
            expect(html).toContain(color);
        expect(html).toContain('Dalam penanganan');
        expect(html).toContain('Perlu ditindaklanjuti');
        expect(html).toContain('Tindakan penanganan');

        // Optional QA artifact: visual fixtures only, never a source of operational data.
        if (process.env.INCIDENT_ALERT_PREVIEW) {
            const manifest = JSON.parse(
                readFileSync('public/build/manifest.json', 'utf8'),
            ) as Record<
                string,
                { file: string; css?: string[]; isEntry?: boolean }
            >;
            const css = new Set(
                Object.values(manifest).flatMap((entry) => [
                    ...(entry.css ?? []),
                    ...(entry.isEntry && entry.file.endsWith('.css')
                        ? [entry.file]
                        : []),
                ]),
            );
            writeFileSync(
                process.env.INCIDENT_ALERT_PREVIEW,
                `<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pratinjau alert insiden</title>${[...css].map((file) => `<link rel="stylesheet" href="/build/${file}">`).join('')}</head><body><main class="admin-dashboard-theme petugas-dashboard-theme mx-auto min-h-dvh max-w-[440px] space-y-4 bg-background p-4 text-foreground"><p class="text-xs text-muted-foreground">Pratinjau warna · data contoh pengujian</p>${html}<h2 class="admin-section-title">To-do hari ini</h2><p class="text-sm text-muted-foreground">Alert tampil sebelum daftar tugas.</p></main></body></html>`,
            );
        }
    });
    it('does not render placeholder alerts when no incidents exist', async () => {
        expect(await render([])).not.toContain('<article');
    });
    it('escapes incident text and reports a larger scoped total', async () => {
        const html = await render(
            [{ ...fixtures[0], description: '<script>unsafe()</script>' }],
            7,
        );
        expect(html).toContain('&lt;script&gt;');
        expect(html).not.toContain('<script>');
        expect(html).toContain('1 dari 7 insiden aktif');
    });
});
