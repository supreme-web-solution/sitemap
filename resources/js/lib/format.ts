const relativeTimeUnits: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_536_000],
    ['month', 2_592_000],
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

/**
 * Format an ISO date as a short relative phrase such as "3 hours ago".
 */
export const formatRelativeTime = (iso: string, now = Date.now()): string => {
    const seconds = Math.round((new Date(iso).getTime() - now) / 1000);
    const formatter = new Intl.RelativeTimeFormat(undefined, {
        numeric: 'auto',
    });

    for (const [unit, size] of relativeTimeUnits) {
        if (Math.abs(seconds) >= size) {
            return formatter.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
};

/**
 * Short clock time for chat bubbles (e.g. "5:38 PM").
 */
export const formatMessageClockTime = (iso: string): string =>
    new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(iso));

const sameCalendarDay = (left: Date, right: Date): boolean =>
    left.getFullYear() === right.getFullYear() &&
    left.getMonth() === right.getMonth() &&
    left.getDate() === right.getDate();

/**
 * WhatsApp-style day chip: Today, Yesterday, or a calendar date.
 */
export const formatChatDayLabel = (iso: string, now = new Date()): string => {
    const date = new Date(iso);
    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);

    if (sameCalendarDay(date, now)) {
        return 'Today';
    }

    if (sameCalendarDay(date, yesterday)) {
        return 'Yesterday';
    }

    return new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
        year: date.getFullYear() === now.getFullYear() ? undefined : 'numeric',
    }).format(date);
};

export const chatDayKey = (iso: string): string => {
    const date = new Date(iso);

    return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
};

export const formatCurrency = (value: number): string =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(value);

export const formatCompactNumber = (value: number): string =>
    new Intl.NumberFormat(undefined, {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);

/**
 * Turn a title into a lowercase, hyphenated URL slug.
 */
export const slugify = (value: string): string =>
    value
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 60);
