export function chartNumber(value: number, maximumFractionDigits = 1): string {
    return Number.isFinite(value)
        ? new Intl.NumberFormat('id-ID', { maximumFractionDigits }).format(
              value,
          )
        : '—';
}

export function chartDate(value: string, withYear = false): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        ...(withYear ? { year: 'numeric' as const } : {}),
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value));
}

export function chartRange(start: string, end: string): string {
    return `${chartDate(start)}–${chartDate(end, true)}`;
}
