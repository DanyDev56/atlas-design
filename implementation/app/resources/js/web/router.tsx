import { Navigate, Route, Routes } from 'react-router-dom';
import { AppShell } from '@/components/layout/AppShell';
import { DashboardPage } from '@/pages/DashboardPage';
import { LoginPage } from '@/pages/LoginPage';
import { OnboardingPage } from '@/pages/OnboardingPage';
import { RegisterPage } from '@/pages/RegisterPage';

export function AppRouter() {
    return (
        <Routes>
            <Route path="/app/login" element={<LoginPage />} />
            <Route path="/app/register" element={<RegisterPage />} />
            <Route path="/app/onboarding" element={<OnboardingPage />} />
            <Route path="/app" element={<AppShell />}>
                <Route index element={<DashboardPage />} />
            </Route>
            <Route path="*" element={<Navigate to="/app" replace />} />
        </Routes>
    );
}
