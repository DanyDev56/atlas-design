import { Link } from 'react-router-dom';
import { WidgetCard, EmptyWidgetMessage } from '@/components/dashboard/WidgetCard';
import { StatusBadge } from '@/components/crm/StatusBadge';
import type { DashboardWidget, BillingPayload } from '@/types/api';
import { formatMoney } from '@/utils/format';

export function BillingWidget({ widget }: { widget: DashboardWidget<BillingPayload> }) {
    const invoices = widget.payload?.recent_invoices ?? [];

    return (
        <WidgetCard title="Facturation récente" dataState={widget.data_state} observedAt={widget.observed_at}>
            {widget.data_state === 'Data' && invoices.length > 0 ? (
                <>
                    <ul className="divide-y divide-atlas-border">
                        {invoices.map((invoice) => (
                            <li key={invoice.invoice_id} className="flex items-center justify-between gap-4 py-3 first:pt-0">
                                <div>
                                    <p className="text-sm font-medium text-atlas-ink">
                                        {invoice.invoice_number ?? `Facture ${invoice.invoice_id.slice(0, 8)}`}
                                    </p>
                                    <div className="mt-1.5">
                                        <StatusBadge
                                            status={
                                                invoice.settlement_status && invoice.settlement_status !== 'Unpaid'
                                                    ? invoice.settlement_status
                                                    : invoice.status
                                            }
                                        />
                                    </div>
                                </div>
                                {invoice.total_cents != null && (
                                    <p className="text-sm font-semibold tabular-nums">
                                        {formatMoney(invoice.total_cents, invoice.currency ?? 'EUR')}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                    <Link to="/app/billing" className="mt-4 inline-flex text-sm font-semibold text-atlas-accent hover:underline">
                        Voir tous les devis →
                    </Link>
                </>
            ) : (
                <EmptyWidgetMessage>
                    <span>Émettez votre premier devis pour commencer à suivre la facturation.</span>
                    <Link to="/app/billing" className="font-semibold text-atlas-accent hover:underline">
                        Voir les devis →
                    </Link>
                </EmptyWidgetMessage>
            )}
        </WidgetCard>
    );
}
