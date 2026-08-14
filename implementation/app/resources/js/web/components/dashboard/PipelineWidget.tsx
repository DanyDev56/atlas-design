import { Link } from 'react-router-dom';
import { WidgetCard, EmptyWidgetMessage } from '@/components/dashboard/WidgetCard';
import type { DashboardWidget, PipelinePayload } from '@/types/api';
import { formatStatus } from '@/utils/format';

const statusOrder = ['Open', 'Qualified', 'Won', 'Lost'];

export function PipelineWidget({ widget }: { widget: DashboardWidget<PipelinePayload> }) {
    const counts = widget.payload?.counts_by_status ?? {};
    const entries = Object.entries(counts)
        .filter(([, count]) => count > 0)
        .sort(([first], [second]) => {
            const firstIndex = statusOrder.indexOf(first);
            const secondIndex = statusOrder.indexOf(second);

            return (firstIndex === -1 ? statusOrder.length : firstIndex) -
                (secondIndex === -1 ? statusOrder.length : secondIndex);
        });
    const largestCount = Math.max(...entries.map(([, count]) => count), 1);

    return (
        <WidgetCard
            title="Pipeline commercial"
            subtitle="Vos opportunités à chaque étape"
            dataState={widget.data_state}
            observedAt={widget.observed_at}
        >
            {widget.data_state === 'Data' && entries.length > 0 ? (
                <div>
                    <ul className="space-y-3">
                        {entries.map(([status, count]) => (
                            <li key={status}>
                                <div className="mb-1.5 flex items-center justify-between text-sm">
                                    <span className="text-atlas-ink-muted">{formatStatus(status)}</span>
                                    <span className="font-semibold tabular-nums text-atlas-ink">{count}</span>
                                </div>
                                <div className="h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                    <div
                                        className="h-full rounded-full bg-atlas-accent"
                                        style={{ width: `${(count / largestCount) * 100}%` }}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                    <Link to="/app/crm" className="mt-5 inline-flex text-sm font-semibold text-atlas-accent hover:underline">
                        Voir le pipeline CRM →
                    </Link>
                </div>
            ) : (
                <EmptyWidgetMessage>
                    <span>Créez un client et une opportunité pour visualiser votre cycle commercial.</span>
                    <Link to="/app/crm" className="font-semibold text-atlas-accent hover:underline">
                        Créer un client →
                    </Link>
                </EmptyWidgetMessage>
            )}
        </WidgetCard>
    );
}
