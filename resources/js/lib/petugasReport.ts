export function formatReportDate(value: string): string {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
    const date = new Date(`${value}T00:00:00Z`);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'long',
              timeZone: 'UTC',
          }).format(date);
}
