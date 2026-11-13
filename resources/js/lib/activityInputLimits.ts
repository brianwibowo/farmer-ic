export type NumericActivityField = {
    key: string;
    label: string;
    type: string;
    max?: number;
    when?: [string, string];
};

export function formatActivityNumber(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 4,
    }).format(value);
}

export function maximumError(
    value: string | number | null | undefined,
    maximum: number | null | undefined,
    label: string,
    unit = '',
): string | undefined {
    if (value === '' || value == null || maximum == null) return undefined;
    const number = Number(value);
    if (!Number.isFinite(number) || number <= maximum) return undefined;
    return `${label} maksimal ${formatActivityNumber(maximum)}${unit ? ` ${unit}` : ''}.`;
}

export function areaMaximum(
    fieldMaximum: number | undefined,
    areaLimitM2: number | null,
    unit: string | undefined,
): number | undefined {
    const locationMaximum =
        areaLimitM2 == null
            ? undefined
            : unit === 'Ha'
              ? areaLimitM2 / 10000
              : areaLimitM2;
    if (fieldMaximum == null) return locationMaximum;
    return locationMaximum == null
        ? fieldMaximum
        : Math.min(fieldMaximum, locationMaximum);
}

export function activityDetailMaximumErrors(
    fields: NumericActivityField[],
    values: Record<string, string>,
    areaLimitM2: number | null,
    quantityKgLimit: number,
): Record<string, string> {
    const errors: Record<string, string> = {};
    for (const field of fields) {
        if (
            field.type !== 'number' ||
            (field.when && values[field.when[0]] !== field.when[1])
        )
            continue;
        const maximum =
            field.key === 'area'
                ? areaMaximum(field.max, areaLimitM2, values.area_unit)
                : field.max;
        const error = maximumError(
            values[field.key],
            maximum,
            field.label,
            field.key === 'area' ? values.area_unit : '',
        );
        if (error) errors[`details.${field.key}`] = error;
    }

    if (values.quantity && values.quantity_unit === 'sak' && values.bag_size) {
        const kg = Number(values.quantity) * Number(values.bag_size);
        if (Number.isFinite(kg) && kg > quantityKgLimit) {
            errors['details.quantity'] =
                `Jumlah setelah konversi maksimal ${formatActivityNumber(quantityKgLimit)} kg.`;
        }
    }
    if (
        areaLimitM2 != null &&
        values.bed_count &&
        values.length_m &&
        values.width_m
    ) {
        // The backend rounds the calculated hectares to whole square metres.
        const area = Math.round(
            Number(values.bed_count) *
                Number(values.length_m) *
                Number(values.width_m),
        );
        if (Number.isFinite(area) && area > areaLimitM2) {
            errors['details.bed_area'] =
                `Luas bedengan maksimal ${formatActivityNumber(areaLimitM2)} m² sesuai lokasi yang dipilih.`;
        }
    }
    return errors;
}
