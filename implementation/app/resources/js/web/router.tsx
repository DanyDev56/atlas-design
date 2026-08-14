import { Navigate, Route, Routes } from 'react-router-dom';
import { AppShell } from '@/components/layout/AppShell';
import { BillingPage } from '@/pages/BillingPage';
import { ClientDetailPage } from '@/pages/ClientDetailPage';
import { CrmClientsPage } from '@/pages/CrmClientsPage';
import { DashboardPage } from '@/pages/DashboardPage';
import { InvoiceDetailPage } from '@/pages/InvoiceDetailPage';
import { LoginPage } from '@/pages/LoginPage';
import { OnboardingPage } from '@/pages/OnboardingPage';
import { OpportunityDetailPage } from '@/pages/OpportunityDetailPage';
import { PublicQuoteAcceptPage } from '@/pages/PublicQuoteAcceptPage';
import { QuoteDetailPage } from '@/pages/QuoteDetailPage';
import { RegisterPage } from '@/pages/RegisterPage';

export function AppRouter() {
    return (
        <Routes>
            <Route path="/app/login" element={<LoginPage />} />
            <Route path="/app/register" element={<RegisterPage />} />
            <Route path="/app/onboarding" element={<OnboardingPage />} />
            <Route path="/app/quotes/accept/:workspaceId/:quoteId" element={<PublicQuoteAcceptPage />} />
            <Route path="/app" element={<AppShell />}>
                <Route index element={<DashboardPage />} />
                <Route path="crm" element={<CrmClientsPage />} />
                <Route path="crm/clients/:clientId" element={<ClientDetailPage />} />
                <Route path="crm/opportunities/:opportunityId" element={<OpportunityDetailPage />} />
                <Route path="billing" element={<BillingPage />} />
                <Route path="billing/quotes/:quoteId" element={<QuoteDetailPage />} />
                <Route path="billing/invoices/:invoiceId" element={<InvoiceDetailPage />} />
            </Route>
            <Route path="*" element={<Navigate to="/app" replace />} />
        </Routes>
    );
}
