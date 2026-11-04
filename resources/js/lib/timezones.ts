/** Common IANA zones for business messaging quiet hours (valid for Laravel `timezone:all`). */
export const timezoneOptions: { value: string; label: string }[] = [
    { value: 'Pacific/Honolulu', label: 'Hawaii' },
    { value: 'America/Anchorage', label: 'Alaska' },
    { value: 'America/Los_Angeles', label: 'Pacific Time (US & Canada)' },
    { value: 'America/Denver', label: 'Mountain Time (US & Canada)' },
    { value: 'America/Phoenix', label: 'Arizona' },
    { value: 'America/Chicago', label: 'Central Time (US & Canada)' },
    { value: 'America/New_York', label: 'Eastern Time (US & Canada)' },
    { value: 'America/Toronto', label: 'Toronto' },
    { value: 'America/Mexico_City', label: 'Mexico City' },
    { value: 'America/Sao_Paulo', label: 'São Paulo' },
    { value: 'America/Buenos_Aires', label: 'Buenos Aires' },
    { value: 'Europe/London', label: 'London' },
    { value: 'Europe/Dublin', label: 'Dublin' },
    { value: 'Europe/Paris', label: 'Paris' },
    { value: 'Europe/Berlin', label: 'Berlin' },
    { value: 'Europe/Amsterdam', label: 'Amsterdam' },
    { value: 'Europe/Madrid', label: 'Madrid' },
    { value: 'Europe/Rome', label: 'Rome' },
    { value: 'Europe/Stockholm', label: 'Stockholm' },
    { value: 'Europe/Warsaw', label: 'Warsaw' },
    { value: 'Europe/Athens', label: 'Athens' },
    { value: 'Africa/Johannesburg', label: 'Johannesburg' },
    { value: 'Africa/Lagos', label: 'Lagos' },
    { value: 'Asia/Dubai', label: 'Dubai' },
    { value: 'Asia/Kolkata', label: 'India' },
    { value: 'Asia/Singapore', label: 'Singapore' },
    { value: 'Asia/Hong_Kong', label: 'Hong Kong' },
    { value: 'Asia/Tokyo', label: 'Tokyo' },
    { value: 'Asia/Seoul', label: 'Seoul' },
    { value: 'Australia/Perth', label: 'Perth' },
    { value: 'Australia/Sydney', label: 'Sydney' },
    { value: 'Pacific/Auckland', label: 'Auckland' },
    { value: 'UTC', label: 'UTC' },
];

export function timezoneOptionsIncluding(current?: string | null): typeof timezoneOptions {
    if (!current || timezoneOptions.some((option) => option.value === current)) {
        return timezoneOptions;
    }

    return [{ value: current, label: current }, ...timezoneOptions];
}
