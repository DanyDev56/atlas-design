import { expect, test } from '@playwright/test';

const userId = '00000000-0000-4000-8000-000000000101';
const workspaceId = '00000000-0000-4000-8000-000000000102';
const sessionToken = 'e2e-first-run-token';

test('inscription, création de l’activité et arrivée sur le dashboard en moins de deux minutes', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Le parcours fonctionnel est identique sur les deux viewports.');

    await page.route('**/api/auth/register', async (route) => {
        await route.fulfill({
            json: {
                user_id: userId,
                status: 'PendingVerification',
                verification_token: 'e2e-verification-token',
            },
        });
    });
    await page.route('**/api/auth/verify-email', async (route) => {
        await route.fulfill({ json: {} });
    });
    await page.route('**/api/auth/login', async (route) => {
        await route.fulfill({
            json: {
                session_id: '00000000-0000-4000-8000-000000000103',
                user_id: userId,
                token: sessionToken,
                expires_at: '2099-01-01T00:00:00+00:00',
            },
        });
    });
    await page.route('**/api/auth/session/context', async (route) => {
        await route.fulfill({ json: { user_id: userId, workspace_id: null } });
    });
    await page.route('**/api/workspaces/first', async (route) => {
        await route.fulfill({ json: { workspace_id: workspaceId, status: 'Active' } });
    });
    await page.route(`**/api/workspaces/${workspaceId}/summary`, async (route) => {
        await route.fulfill({
            json: {
                workspace_id: workspaceId,
                display_name: 'Studio Première Visite',
                access_state: 'Active',
                version: 1,
            },
        });
    });
    await page.route(`**/api/workspaces/${workspaceId}/notifications/unread-count`, async (route) => {
        await route.fulfill({ json: { unread_count: 0 } });
    });
    await page.route(`**/api/workspaces/${workspaceId}/dashboard`, async (route) => {
        const emptyWidget = (sourceDomain: string) => ({
            source_domain: sourceDomain,
            data_state: 'NoData',
            observed_at: null,
            payload: null,
        });

        await route.fulfill({
            json: {
                workspace_id: workspaceId,
                advisor_priority: emptyWidget('Advisor'),
                business_health: emptyWidget('BusinessHealth'),
                pipeline: emptyWidget('CRM'),
                billing: emptyWidget('Billing'),
                measured_activity: emptyWidget('Analytics'),
                notifications: emptyWidget('Notifications'),
            },
        });
    });

    const startedAt = Date.now();

    await page.goto('/app/register');
    await page.getByLabel('Email').fill('premiere-visite@atlas.test');
    await page.getByLabel('Nom affiché').fill('Camille Martin');
    await page.getByLabel('Mot de passe').fill('PremiereVisite2026!');
    await page.getByRole('button', { name: 'Créer mon compte' }).click();

    await expect(page).toHaveURL(/\/app\/onboarding$/);
    await expect(page.getByRole('heading', { name: 'Configurez votre activité' })).toBeVisible();
    await expect(page.getByLabel('Étape 2 sur 2')).toBeVisible();

    await page.getByLabel('Nom de votre activité').fill('Studio Première Visite');
    await page.getByRole('button', { name: 'Accéder à mon tableau de bord' }).click();

    await expect(page).toHaveURL(/\/app\/?$/);
    await expect(page.getByRole('heading', { name: 'Bonjour, Premiere-visite' })).toBeVisible();
    await expect(page.getByText('Studio Première Visite', { exact: true })).toBeVisible();
    expect(Date.now() - startedAt).toBeLessThan(120_000);
});
