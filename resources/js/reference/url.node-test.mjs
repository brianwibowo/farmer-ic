import assert from 'node:assert/strict';
import { test } from 'node:test';

// Dynamic import keeps the initial RED a behavioral assertion, not a loader error.
const { referenceUrl } = await import('./url.mjs').catch(() => ({ referenceUrl: () => undefined }));
void test('historical home is isolated under /referensi', () => {
    assert.equal(referenceUrl('/'), '/referensi');
});

void test('known pages, queries and fragments remain inside the snapshot', () => {
    for (const path of ['/kebun', '/kebun/detail', '/kebun/aktivitas', '/aktivitas', '/rincian-aktivitas', '/laporan', '/finance', '/ai']) {
        assert.equal(referenceUrl(`${path}?id=kebun-001#detail`), `/referensi${path}?id=kebun-001#detail`);
        assert.equal(referenceUrl(`/referensi${path}`), `/referensi${path}`);
    }
    assert.equal(referenceUrl('/bypass'), '/referensi/ai');
    assert.equal(referenceUrl('/referensi/'), '/referensi');
});

void test('unsafe, unknown and malformed URLs never leave the reference root', () => {
    for (const path of ['https://example.com/kebun', '//example.com', 'javascript:alert(1)', '/login', '/finance/export', '/kebun/../login', '/referensi/../../login', '/kebun%2F..%2Flogin', '/kebun\\\\login', null, { url: '/login' }]) {
        assert.equal(referenceUrl(path), '/referensi');
    }
});

void test('nested back links are rewritten and cannot escape', () => {
    const good = new URL(referenceUrl('/rincian-aktivitas?id=1&back=%2Fkebun%2Faktivitas%3Fkebun%3Da'), 'http://reference.test');
    assert.equal(good.searchParams.get('back'), '/referensi/kebun/aktivitas?kebun=a');
    const bad = new URL(referenceUrl('/rincian-aktivitas?back=https%3A%2F%2Fevil.test'), 'http://reference.test');
    assert.equal(bad.searchParams.get('back'), '/referensi');
});
