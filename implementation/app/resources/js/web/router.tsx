import { Navigate, Route, Routes } from 'react-router-dom';
import { AppShell } from '@/components/layout/AppShell';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { AdvisorPage } from '@/pages/AdvisorPage';
import { AcceptInvitationPage } from '@/pages/AcceptInvitationPage';
import { BillingHistoryImportPage } from '@/pages/BillingHistoryImportPage';
import { BillingPage } from '@/pages/BillingPage';
import { BusinessHealthPage } from '@/pages/BusinessHealthPage';
import { ClientDetailPage } from '@/pages/ClientDetailPage';
import { ClientHistoryImportPage } from '@/pages/ClientHistoryImportPage';
import { CrmClientsPage } from '@/pages/CrmClientsPage';
import { DashboardPage } from '@/pages/DashboardPage';
import { InvoiceDetailPage } from '@/pages/InvoiceDetailPage';
import { ForgotPasswordPage } from '@/pages/ForgotPasswordPage';
import { LoginPage } from '@/pages/LoginPage';
import { ResetPasswordPage } from '@/pages/ResetPasswordPage';
import { NotificationsPage } from '@/pages/NotificationsPage';
import { OnboardingPage } from '@/pages/OnboardingPage';
import { OpportunityDetailPage } from '@/pages/OpportunityDetailPage';
import { PublicQuoteAcceptPage } from '@/pages/PublicQuoteAcceptPage';
import { QuoteDetailPage } from '@/pages/QuoteDetailPage';
import { RegisterPage } from '@/pages/RegisterPage';
import { VerifyEmailPage } from '@/pages/VerifyEmailPage';
import { SettingsPage } from '@/pages/SettingsPage';

export function AppRouter() {
    return (
        <Routes>
            <Route path="/app/login" element={<LoginPage />} />
            <Route path="/app/register" element={<RegisterPage />} />
            <Route path="/app/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/app/reset-password" element={<ResetPasswordPage />} />
            <Route path="/app/verify-email" element={<VerifyEmailPage />} />
            <Route path="/app/invitations/:invitationId/accept" element={<AcceptInvitationPage />} />
            <Route path="/app/onboarding" element={<OnboardingPage />} />
            <Route path="/app/quotes/accept/:workspaceId/:quoteId" element={<PublicQuoteAcceptPage />} />
            <Route path="/app" element={<RequireAuth><AppShell /></RequireAuth>}>
                <Route index element={<DashboardPage />} />
                <Route path="crm" element={<CrmClientsPage />} />
                <Route path="crm/import" element={<ClientHistoryImportPage />} />
                <Route path="crm/clients/:clientId" element={<ClientDetailPage />} />
                <Route path="crm/opportunities/:opportunityId" element={<OpportunityDetailPage />} />
                <Route path="billing" element={<BillingPage />} />
                <Route path="billing/import" element={<BillingHistoryImportPage />} />
                <Route path="billing/quotes/:quoteId" element={<QuoteDetailPage />} />
                <Route path="billing/invoices/:invoiceId" element={<InvoiceDetailPage />} />
                <Route path="health" element={<BusinessHealthPage />} />
                <Route path="advisor" element={<AdvisorPage />} />
                <Route path="settings" element={<SettingsPage />} />
                <Route path="notifications" element={<NotificationsPage />} />
            </Route>
            <Route path="*" element={<Navigate to="/app" replace />} />
        </Routes>
    );
}
