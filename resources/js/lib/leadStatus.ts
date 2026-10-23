import type { LeadStatus } from '@/types';

export const leadStatuses: { value: LeadStatus; label: string }[] = [
    { value: 'new', label: 'New' },
    { value: 'site_generated', label: 'Site generated' },
    { value: 'contacted', label: 'Contacted' },
    { value: 'interested', label: 'Interested' },
    { value: 'won', label: 'Won' },
    { value: 'lost', label: 'Lost' },
];

export const leadStatusLabel = (status: LeadStatus): string =>
    leadStatuses.find((option) => option.value === status)?.label ?? status;

export const leadStatusClasses: Record<LeadStatus, string> = {
    new: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    site_generated:
        'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    contacted:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    interested:
        'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
    won: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    lost: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
};
