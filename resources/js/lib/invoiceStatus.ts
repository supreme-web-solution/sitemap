import type { InvoiceStatus } from '@/types';

export const invoiceStatusClasses = (
    status: InvoiceStatus,
    isOverdue = false,
): string => {
    if (status === 'open' && isOverdue) {
        return 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300';
    }

    return {
        draft: 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
        open: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        paid: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        void: 'bg-slate-100 text-slate-500 line-through dark:bg-slate-500/15 dark:text-slate-400',
        refunded: 'bg-slate-100 text-slate-500 dark:bg-slate-500/15 dark:text-slate-400',
    }[status];
};

export const invoiceStatusLabel = (
    status: InvoiceStatus,
    label: string,
    isOverdue = false,
): string => (status === 'open' && isOverdue ? 'Overdue' : label);
