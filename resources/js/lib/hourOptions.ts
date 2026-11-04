export type HourOption = { value: string; label: string };

/** 0–23 with 12-hour labels for quiet-hour pickers. */
export const hourOptions: HourOption[] = Array.from({ length: 24 }, (_, hour) => ({
    value: String(hour),
    label: formatHourLabel(hour),
}));

export function formatHourLabel(hour: number): string {
    const normalized = ((hour % 24) + 24) % 24;
    const period = normalized < 12 ? 'AM' : 'PM';
    const display = normalized % 12 === 0 ? 12 : normalized % 12;

    return `${display}:00 ${period}`;
}
