export type AreaUnit = 'ha' | 'm2';

/** Area arithmetic uses whole m², matching numeric(12,4) hectares in storage. */
export function squareMetres(
    value: string,
    unit: AreaUnit = 'ha',
): number | null {
    const parts = value
        .trim()
        .replace(',', '.')
        .replace(/^\+/, '')
        .replace(/^\./, '0.')
        .match(/^([0-9]{1,12})(?:\.([0-9]{1,4}))?$/);
    if (!parts) return null;
    const fraction = Number((parts[2] ?? '').padEnd(4, '0'));
    if (unit === 'm2') return parts[2] === undefined ? Number(parts[1]) : null;
    const metres = Number(parts[1]) * 10000 + fraction;
    return Number.isSafeInteger(metres) ? metres : null;
}

export function areaValue(metres: number, unit: AreaUnit): string {
    if (unit === 'm2') return String(metres);
    const fraction = String(metres % 10000)
        .padStart(4, '0')
        .replace(/0+$/, '');
    return `${Math.floor(metres / 10000)}${fraction ? `.${fraction}` : ''}`;
}
