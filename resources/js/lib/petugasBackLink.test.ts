import { describe, expect, it } from 'vite-plus/test';
import {
    extractPetugasBackParam,
    petugasBackLabel,
    resolvePetugasBackHref,
    withPetugasBack,
} from './petugasBackLink';

describe('petugasBackLink helpers', () => {
    it('extracts and resolves safe petugas back paths while rejecting external or traversal targets', () => {
        expect(
            resolvePetugasBackHref('/petugas/penanaman', '/petugas/laporan'),
        ).toBe('/petugas/laporan');

        expect(
            resolvePetugasBackHref(
                '/petugas/penanaman?back=%2Fpetugas',
                '/petugas/laporan',
            ),
        ).toBe('/petugas');

        expect(
            resolvePetugasBackHref(
                '/petugas/penanaman?back=%2Fpetugas%2Flaporan%3Fsource%3Dsop%26page%3D2',
                '/petugas/laporan',
            ),
        ).toBe('/petugas/laporan?source=sop&page=2');

        for (const bad of [
            'https://evil.example/petugas',
            '//evil.example/petugas',
            '/admin/dashboard',
            '/petugas\\@evil.example',
        ]) {
            expect(
                resolvePetugasBackHref(
                    `/petugas/penanaman?back=${encodeURIComponent(bad)}`,
                    '/petugas/laporan',
                ),
            ).toBe('/petugas/laporan');
        }
    });

    it('appends clean back parameters without nesting existing back params', () => {
        expect(withPetugasBack('/petugas/penanaman', null)).toBe(
            '/petugas/penanaman',
        );
        expect(
            withPetugasBack(
                '/petugas/penanaman?work_date=2026-11-17',
                '/petugas',
            ),
        ).toBe('/petugas/penanaman?work_date=2026-11-17&back=%2Fpetugas');
        expect(
            withPetugasBack(
                '/petugas/incidents/ticket-1',
                '/petugas/incidents?view=resolved&back=%2Fpetugas',
            ),
        ).toBe(
            '/petugas/incidents/ticket-1?back=%2Fpetugas%2Fincidents%3Fview%3Dresolved',
        );
        expect(
            extractPetugasBackParam('/admin/incidents?back=%2Fpetugas'),
        ).toBeNull();
    });

    it('maps resolved back URLs to readable Indonesian labels', () => {
        expect(petugasBackLabel('/petugas', 'Fallback')).toBe(
            'Kembali ke beranda',
        );
        expect(
            petugasBackLabel('/petugas/laporan?source=sop', 'Fallback'),
        ).toBe('Kembali ke laporan');
        expect(
            petugasBackLabel(
                '/petugas/laporan/harian?work_date=2026-11-17',
                'Fallback',
            ),
        ).toBe('Kembali ke laporan harian');
        expect(petugasBackLabel('/petugas/aktivitas', 'Fallback')).toBe(
            'Kembali ke daftar tugas',
        );
        expect(petugasBackLabel('/petugas/incidents', 'Fallback')).toBe(
            'Kembali ke daftar tiket insiden',
        );
        expect(
            petugasBackLabel('/petugas/incidents/ticket-1', 'Fallback'),
        ).toBe('Kembali ke tiket insiden');
    });
});
