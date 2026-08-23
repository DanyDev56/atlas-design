import { expect, test } from '@playwright/test';

test('la landing Early Access présente la promesse, le produit et une inscription claire', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveTitle(/Atlas — Pilotez votre activité/);
    await expect(page.getByRole('heading', { name: /Votre activité\. Enfin lisible\./ })).toBeVisible();
    await expect(page.getByText('Early Access ouvert', { exact: true })).toBeVisible();
    await expect(page.getByText('30 jours complets', { exact: true })).toBeVisible();
    await expect(page.getByText('Sans carte bancaire', { exact: true })).toBeVisible();

    const header = page.getByRole('banner');
    const mobileMenu = header.getByRole('button', { name: 'Ouvrir le menu' });
    if (await mobileMenu.isVisible()) {
        await mobileMenu.click();
        await expect(header.getByRole('navigation', { name: 'Navigation mobile' })).toBeVisible();
        await header.getByRole('button', { name: 'Fermer le menu' }).click();
        await expect(header.getByRole('navigation', { name: 'Navigation mobile' })).toBeHidden();
    } else {
        await expect(header.getByRole('navigation', { name: 'Navigation principale du site' })).toBeVisible();
    }

    const registrationLinks = page.getByRole('link', { name: /Démarrer gratuitement|Essayer Atlas|Rejoindre l’Early Access/ });
    await expect(registrationLinks.first()).toHaveAttribute('href', '/app/register');

    await page.locator('#fonctionnalites').scrollIntoViewIfNeeded();
    await expect(page.getByRole('heading', { name: 'Une facturation vraiment suivie' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Des priorités, pas du bruit' })).toBeVisible();

    await page.locator('#acces-anticipe').scrollIntoViewIfNeeded();
    await expect(page.getByText('30 jours', { exact: true }).last()).toBeVisible();
    await expect(page.getByText(/Le tarif Atlas Solo est encore en validation/)).toBeVisible();
    await expect(page.getByText(/Aucun prélèvement automatique n’est programmé/)).toBeVisible();
});
