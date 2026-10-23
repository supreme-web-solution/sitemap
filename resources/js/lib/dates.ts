/**
 * Get a local calendar date (YYYY-MM-DD) a number of days from today.
 */
export const dateFromToday = (days: number): string => {
    const date = new Date();

    date.setDate(date.getDate() + days);

    return date.toLocaleDateString('en-CA');
};
