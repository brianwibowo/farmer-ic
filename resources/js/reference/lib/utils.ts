// Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/lib/utils.ts
import type { InertiaLinkProps } from '@/reference/inertia';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}
