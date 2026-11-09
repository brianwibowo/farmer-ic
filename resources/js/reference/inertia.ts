import { Head, Link as InertiaLink, router as inertiaRouter, usePage as inertiaPage } from '@inertiajs/vue3';
import { defineComponent, h } from 'vue';
import { referenceUrl } from './url.mjs';

export { Head };
export type { InertiaLinkProps } from '@inertiajs/vue3';

/** Local adapter, imported by the snapshot only. No monkey-patching. */
export const Link = defineComponent({
    inheritAttrs: false,
    props: { href: { type: String, default: '/referensi' } },
    setup(props, { attrs, slots }) {
        return () => h(InertiaLink, { ...attrs, href: referenceUrl(props.href), method: 'get' }, slots);
    },
});

export const router = {
    visit(url: string): void { inertiaRouter.visit(referenceUrl(url), { method: 'get' }); },
};

/** Deliberately expose fixture identity only; retain reactive current URL for filters. */
export function usePage<T = Record<string, unknown>>() {
    const livePage = inertiaPage();
    const fixtureProps = {
        auth: { user: { name: 'Petani IC' } },
        account: { name: 'Petani IC' },
        pendingInvitations: [],
        reference: { sourceCommit: '73f2555d91d0fb2d6acb7bc70813921edd479ea4', readOnly: true, basePath: '/referensi' },
    };
    return {
        get url() { return livePage.url; },
        props: fixtureProps as typeof fixtureProps & T,
    };
}
