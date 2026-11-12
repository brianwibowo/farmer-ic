export function number(value: number, digits = 3): string {
    return new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: digits,
    }).format(value);
}

export function date(value: string): string {
    const parsed = new Date(
        /^\d{4}-\d{2}-\d{2}$/.test(value) ? value + 'T00:00:00+07:00' : value,
    );
    if (Number.isNaN(parsed.getTime())) return 'Belum ditentukan';
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    }).format(parsed);
}
