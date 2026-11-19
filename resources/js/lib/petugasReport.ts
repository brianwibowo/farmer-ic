import type { DailyReportActivity } from '@/types/daily-activity';
import { squareMetres } from './landArea';

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

type ReportDetail = DailyReportActivity['details'][number];

const areaLabels = new Map([
    ['Luas (ha)', 'Luas'],
    ['Luas terdampak (ha)', 'Luas terdampak'],
    ['Luas panen (ha)', 'Luas panen'],
]);

/**
 * Present one recorded area in m² without changing the stored detail values.
 * Original input takes precedence; older reports can use their stored hectares.
 */
export function formatReportDetails(details: ReportDetail[]): ReportDetail[] {
    const input = details.find((detail) => detail.label === 'Luas input');
    const unit = details
        .find((detail) => detail.label === 'Satuan luas')
        ?.value.toString()
        .trim()
        .toLowerCase();
    const firstArea = details.find((detail) => areaLabels.has(detail.label));
    const inputUnit =
        unit === 'ha' ? 'ha' : unit === 'm²' || unit === 'm2' ? 'm2' : null;
    const inputValue = input?.value.toString().replace(/[.,]0+$/, '');
    const inputMetres =
        inputValue !== undefined && inputUnit !== null
            ? squareMetres(inputValue, inputUnit)
            : null;
    const formatArea = (
        metres: number | null,
        useInputFallback = false,
    ): string => {
        if (metres !== null) {
            return `${new Intl.NumberFormat('id-ID').format(metres)} m²`;
        }
        if (useInputFallback && input && inputUnit === null) {
            return `${input.value} (satuan tidak dapat dipastikan)`;
        }
        return 'Tidak dapat dikonversi';
    };

    return details.flatMap((detail) => {
        if (detail.label === 'Satuan luas') return [];
        if (detail.label === 'Luas input') {
            return firstArea
                ? []
                : [{ label: 'Luas', value: formatArea(inputMetres, true) }];
        }
        const label = areaLabels.get(detail.label);
        if (label) {
            const storedMetres = squareMetres(detail.value.toString(), 'ha');
            const isFirstArea = detail === firstArea;
            const metres = isFirstArea
                ? (inputMetres ?? storedMetres)
                : storedMetres;
            return [{ label, value: formatArea(metres, isFirstArea) }];
        }
        return [detail];
    });
}
