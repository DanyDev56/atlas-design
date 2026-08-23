import { formatStatus } from '@/utils/format';

const statusStyles: Record<string, string> = {
    Open: 'border-blue-200/70 bg-blue-50 text-blue-700 before:bg-blue-500',
    Qualified: 'border-teal-200 bg-atlas-accent-soft text-atlas-accent before:bg-atlas-accent',
    Won: 'border-emerald-200 bg-emerald-50 text-emerald-700 before:bg-emerald-500',
    Lost: 'border-slate-200 bg-slate-100 text-slate-600 before:bg-slate-400',
    Draft: 'border-amber-200 bg-amber-50 text-amber-800 before:bg-amber-500',
    Sent: 'border-indigo-200 bg-indigo-50 text-indigo-700 before:bg-indigo-500',
    Accepted: 'border-emerald-200 bg-emerald-50 text-emerald-700 before:bg-emerald-500',
    Issued: 'border-indigo-200 bg-indigo-50 text-indigo-700 before:bg-indigo-500',
    Unpaid: 'border-amber-200 bg-amber-50 text-amber-800 before:bg-amber-500',
    PartiallyPaid: 'border-amber-200 bg-amber-50 text-amber-800 before:bg-amber-500',
    Paid: 'border-emerald-200 bg-emerald-50 text-emerald-700 before:bg-emerald-500',
    Overdue: 'border-rose-200 bg-rose-50 text-rose-800 before:bg-rose-500',
    Active: 'border-teal-200 bg-atlas-accent-soft text-atlas-accent before:bg-atlas-accent',
};

export function StatusBadge({ status }: { status: string }) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold before:size-1.5 before:rounded-full ${
                statusStyles[status] ?? 'border-slate-200 bg-slate-100 text-atlas-ink-muted before:bg-slate-400'
            }`}
        >
            {formatStatus(status)}
        </span>
    );
}
