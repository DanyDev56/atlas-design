import { expect, test } from '@playwright/test';

test('les pages d’authentification permettent de découvrir la landing', async ({ page }) => {
    await page.goto('/app/register');

    const landingLink = page.getByRole('link', { name: 'Découvrir Atlas', exact: true });
    await expect(landingLink).toBeVisible();
    await expect(landingLink).toHaveAttribute('href', '/');
});

test('le devis public présente Atlas sans concurrencer son action principale', async ({ page }) => {
    await page.goto('/app/quotes/accept/workspace/quote');

    await expect(page.getByRole('heading', { name: 'Votre devis' })).toBeVisible();
    const landingLink = page.getByRole('link', { name: 'Propulsé par Atlas', exact: true });
    await expect(landingLink).toBeVisible();
    await expect(landingLink).toHaveAttribute('href', '/');
});
