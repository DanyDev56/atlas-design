import { WidgetCard, EmptyWidgetMessage } from '@/components/dashboard/WidgetCard';
import type { DashboardWidget, PipelinePayload } from '@/types/api';

export function PipelineWidget({ widget }: { widget: DashboardWidget<PipelinePayload> }) {
    const counts = widget.payload?.counts_by_status ?? {};
    const entries = Object.entries(counts).filter(([, count]) => count > 0);

    return (
        <WidgetCard
            title="Pipeline commercial"
            subtitle="CRM"
            dataState={widget.data_state}
            observedAt={widget.observed_at}
        >
            {widget.data_state === 'Data' && entries.length > 0 ? (
                <ul className="space-y-2">
                    {entries.map(([status, count]) => (
                        <li key={status} className="flex items-center justify-between text-sm">
                            <span className="text-atlas-ink-muted">{status}</span>
                            <span className="font-semibold tabular-nums text-atlas-ink">{count}</span>
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyWidgetMessage>
                    Créez un client et une opportunité pour voir votre pipeline ici.
                </EmptyWidgetMessage>
            )}
        </WidgetCard>
    );
}
