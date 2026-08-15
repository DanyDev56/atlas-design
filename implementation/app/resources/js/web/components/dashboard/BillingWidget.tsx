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
                            <li key={invoice.invoice_id}>
                                <Link
                                    to={`/app/billing/invoices/${invoice.invoice_id}`}
                                    className="group -mx-2 flex min-h-16 items-center justify-between gap-4 rounded-xl px-2 py-3 transition-colors first:pt-0 hover:bg-white/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-atlas-accent/30"
                                >
                                    <div>
                                        <p className="text-sm font-medium text-atlas-ink group-hover:text-atlas-accent">
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
                                    <div className="flex items-center gap-3">
                                        {invoice.total_cents != null && (
                                            <p className="text-sm font-semibold tabular-nums">
                                                {formatMoney(invoice.total_cents, invoice.currency ?? 'EUR')}
                                            </p>
                                        )}
                                        <span aria-hidden="true" className="text-atlas-ink-muted transition-transform group-hover:translate-x-0.5">→</span>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                    <Link to="/app/billing" className="mt-4 inline-flex text-sm font-semibold text-atlas-accent hover:underline">
                        Voir toute la facturation →
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
