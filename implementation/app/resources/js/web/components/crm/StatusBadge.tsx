import { formatStatus } from '@/utils/format';

const statusStyles: Record<string, string> = {
    Open: 'bg-blue-50 text-blue-700',
    Qualified: 'bg-atlas-accent-soft text-atlas-accent',
    Won: 'bg-emerald-50 text-emerald-700',
    Lost: 'bg-slate-100 text-slate-600',
    Draft: 'bg-amber-50 text-amber-800',
    Sent: 'bg-indigo-50 text-indigo-700',
    Accepted: 'bg-emerald-50 text-emerald-700',
    Issued: 'bg-indigo-50 text-indigo-700',
    Unpaid: 'bg-amber-50 text-amber-800',
    PartiallyPaid: 'bg-amber-50 text-amber-800',
    Paid: 'bg-emerald-50 text-emerald-700',
    Active: 'bg-atlas-accent-soft text-atlas-accent',
};

export function StatusBadge({ status }: { status: string }) {
    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${
                statusStyles[status] ?? 'bg-slate-100 text-atlas-ink-muted'
            }`}
        >
            {formatStatus(status)}
        </span>
    );
}
