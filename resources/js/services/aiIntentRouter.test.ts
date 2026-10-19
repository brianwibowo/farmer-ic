import { describe, expect, it } from 'vitest';
import { routeAiPrompt } from './aiIntentRouter';

describe('ai intent router', () => {
    it.each([
        'finansial bulan ini',
        'finansialnya bulan ini',
        'data keuangan bulan ini',
        'ringkasan pemasukan periode ini',
        'berapa keuntungan saya hari ini',
        'berapa laba saya hari ini',
    ])('routes current financial requests to knowledge: %s', (prompt) => {
        const route = routeAiPrompt(prompt);

        expect(route.intent).toBe('knowledge');
        expect(route.reason).toBe('current_finance_unavailable');
        expect(route.domain).toBe('finance');
    });

    it('keeps explicit historical data lookups on the data path', () => {
        const route = routeAiPrompt('data keuangan Q3');

        expect(route.intent).toBe('data');
        expect(route.period).toBe('quarter');
        expect(route.reason).toBe('explicit_data_lookup');
    });

    it('does not turn a data display request into image generation', () => {
        const route = routeAiPrompt('tampilkan kondisi kebun saya');

        expect(route.intent).toBe('data');
        expect(route.reason).toBe('explicit_data_lookup');
    });

    it('keeps explicit drawing requests on the visual path', () => {
        const route = routeAiPrompt('gambarkan kondisi lahan saya');

        expect(route.intent).toBe('infographic');
        expect(route.reason).toBe('explicit_visual_request');
    });

    it('routes agronomy guidance to the LLM path', () => {
        const route = routeAiPrompt('berapa dosis pupuk jagung yang sesuai?');

        expect(route.intent).toBe('llm');
        expect(route.reason).toBe('agriculture_guidance');
    });

    it.each(['halo', 'hai, asisten'])(
        'does not ask clarification for greetings: %s',
        (prompt) => {
            const route = routeAiPrompt(prompt);

            expect(route.intent).toBe('llm');
            expect(route.needsClarification).toBe(false);
        },
    );

    it('flags a short non-greeting prompt for clarification', () => {
        const route = routeAiPrompt('tolong bantu');

        expect(route.intent).toBe('llm');
        expect(route.needsClarification).toBe(true);
    });

    it('routes explicit visual agriculture requests to infographic', () => {
        const route = routeAiPrompt(
            'Infografis: Panduan Pemupukan Jagung sesuai Fase',
        );

        expect(route.intent).toBe('infographic');
        expect(route.reason).toBe('explicit_visual_request');
    });

    it('rejects an unrelated visual request before it reaches the LLM', () => {
        const route = routeAiPrompt('buat poster sepak bola');

        expect(route.intent).toBe('out_of_scope');
        expect(route.reason).toBe('unsupported_topic');
    });

    it('rejects direct coding requests even when they mention farming', () => {
        const route = routeAiPrompt('buat kode untuk kebun');

        expect(route.intent).toBe('out_of_scope');
        expect(route.reason).toBe('unsupported_topic');
    });
});
