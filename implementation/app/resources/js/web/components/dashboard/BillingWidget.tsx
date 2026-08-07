import { WidgetCard, EmptyWidgetMessage } from '@/components/dashboard/WidgetCard';
import type { DashboardWidget, BillingPayload } from '@/types/api';

function formatMoney(cents: number, currency = 'EUR'): string {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency }).format(cents / 100);
}

export function BillingWidget({ widget }: { widget: DashboardWidget<BillingPayload> }) {
    const invoices = widget.payload?.recent_invoices ?? [];

    return (
        <WidgetCard
            title="Facturation récente"
            subtitle="Billing"
            dataState={widget.data_state}
            observedAt={widget.observed_at}
        >
            {widget.data_state === 'Data' && invoices.length > 0 ? (
                <ul className="divide-y divide-atlas-border">
                    {invoices.map((invoice) => (
                        <li key={invoice.invoice_id} className="flex items-center justify-between py-3 first:pt-0">
                            <div>
                                <p className="text-sm font-medium text-atlas-ink">
                                    {invoice.number ?? invoice.invoice_id.slice(0, 8)}
                                </p>
                                <p className="text-xs text-atlas-ink-muted">{invoice.status}</p>
                            </div>
                            {invoice.total_cents != null && (
                                <p className="text-sm font-semibold tabular-nums">
                                    {formatMoney(invoice.total_cents, invoice.currency)}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyWidgetMessage>
                    Émettez votre premier devis et facture pour suivre la trésorerie ici.
                </EmptyWidgetMessage>
            )}
        </WidgetCard>
    );
}
