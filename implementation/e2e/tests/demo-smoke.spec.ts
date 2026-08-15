import { expect, test, type Page } from '@playwright/test';

const email = process.env.ATLAS_DEMO_EMAIL ?? 'demo@atlas.test';
const password = process.env.ATLAS_DEMO_PASSWORD ?? 'DemoAtlas2026!';
const emptyEmail = process.env.ATLAS_EMPTY_DEMO_EMAIL ?? 'demo-empty@atlas.test';
const emptyPassword = process.env.ATLAS_EMPTY_DEMO_PASSWORD ?? 'DemoEmpty2026!';

async function login(page: Page, accountEmail = email, accountPassword = password) {
    await page.goto('/app/login');
    await page.getByLabel('Email').fill(accountEmail);
    await page.getByLabel('Mot de passe').fill(accountPassword);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/app\/?$/);
    await expect(page.getByRole('heading', { name: /^Bonjour/ })).toBeVisible();
}

async function navigateFromShell(page: Page, destination: string) {
    const mobileMenu = page.getByRole('button', { name: 'Ouvrir le menu' });

    if (await mobileMenu.isVisible()) {
        await mobileMenu.click();
    }

    await page.getByRole('link', { name: destination, exact: true }).click();
}

test.describe('scénario démo complet', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

test('le dashboard présente la priorité et les indicateurs essentiels', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Priorité du jour' })).toBeVisible();
    await expect(page.getByRole('heading', { name: "Santé de l'activité" })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Pipeline commercial' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Facturation récente' })).toBeVisible();

    const recentInvoice = page.locator('a[href^="/app/billing/invoices/"]').first();
    await expect(recentInvoice).toBeVisible();
    await recentInvoice.click();
    await expect(page).toHaveURL(/\/app\/billing\/invoices\//);
    await expect(page.getByRole('region', { name: 'Prestations facturées' })).toBeVisible();
});

test('les données démo rendent les principaux dossiers identifiables', async ({ page }) => {
    await navigateFromShell(page, 'CRM');
    await expect(page.getByRole('heading', { name: 'Clients' })).toBeVisible();
    await expect(page.getByText('Les Ateliers du Marais', { exact: true })).toBeVisible();
    await expect(page.getByText('Nova Conseil', { exact: true })).toBeVisible();
    await expect(page.getByText('Collectif Cobalt', { exact: true })).toBeVisible();

    await page.getByRole('link').filter({ hasText: 'Les Ateliers du Marais' }).click();
    await expect(page.getByRole('heading', { name: 'Contacts' })).toBeVisible();
    await expect(page.getByText('Camille Martin', { exact: true })).toBeVisible();
    await expect(page.getByText('Julien Morel', { exact: true })).toBeVisible();
    await expect(page.getByText('Principal', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Retirer Camille Martin comme contact principal' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Définir Julien Morel comme contact principal' })).toBeVisible();

    await page.getByRole('button', { name: 'Nouvelle opportunité' }).click();
    await expect(page.getByLabel('Contact associé (optionnel)').locator('option:checked'))
        .toHaveText('Camille Martin — principal');

    await navigateFromShell(page, 'Facturation');
    await expect(page.getByRole('heading', { name: 'Facturation' })).toBeVisible();
    await expect(page.getByText('Maison Lumen', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Cabinet Rivoli', { exact: true }).first()).toBeVisible();
});

test('la navigation mobile reste utilisable sans débordement horizontal', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'Contrôle réservé au viewport mobile.');

    await expect(page.getByRole('button', { name: 'Ouvrir le menu' })).toBeVisible();
    await page.getByRole('button', { name: 'Ouvrir le menu' }).click();
    await expect(page.getByRole('dialog', { name: 'Navigation principale' })).toBeVisible();
    await page.getByRole('link', { name: 'Facturation', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Facturation' })).toBeVisible();

    const hasHorizontalOverflow = await page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
    );
    expect(hasHorizontalOverflow).toBe(false);
});

test('le devis brouillon des Ateliers est accessible sans ambiguïté', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Le même parcours métier suffit sur un viewport.');

    await navigateFromShell(page, 'CRM');
    await page.getByRole('link').filter({ hasText: 'Les Ateliers du Marais' }).click();
    await expect(page.getByRole('heading', { name: 'Les Ateliers du Marais' })).toBeVisible();
    await expect(page.getByText('Audit express à qualifier', { exact: true })).toBeVisible();

    await page.getByRole('link').filter({ hasText: 'Refonte identité visuelle' }).click();
    await expect(page.getByRole('heading', { name: 'Refonte identité visuelle' })).toBeVisible();
    await page.getByRole('link', { name: 'Vérifier et envoyer' }).click();
    await expect(page.getByRole('button', { name: 'Envoyer au client' })).toBeVisible();
});

test('les trois états de facturation actionnables ouvrent le bon écran', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Le même parcours métier suffit sur un viewport.');

    await navigateFromShell(page, 'Facturation');

    const quotes = page.getByRole('region', { name: 'Devis' });
    await quotes.getByRole('link').filter({ hasText: 'Nova Conseil' }).click();
    await expect(page.getByRole('button', { name: 'Créer la facture' })).toBeVisible();

    await page.getByRole('link', { name: 'Retour à la facturation' }).click();
    const invoices = page.getByRole('region', { name: 'Factures' });
    await invoices.getByRole('link').filter({ hasText: 'Cabinet Rivoli' }).click();
    await expect(page.getByRole('button', { name: 'Émettre la facture' })).toBeVisible();

    await page.getByRole('link', { name: 'Retour à la facturation' }).click();
    await page.getByRole('region', { name: 'Factures' })
        .getByRole('link')
        .filter({ hasText: 'Collectif Cobalt' })
        .click();
    await expect(page.getByRole('heading', { name: 'Enregistrer un paiement' })).toBeVisible();
    await expect(page.getByText('Reste à encaisser', { exact: true })).toBeVisible();
});
});

test.describe('scénario démo vide', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, emptyEmail, emptyPassword);
    });

    test('chaque destination explique clairement comment démarrer', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Priorité du jour' })).toBeVisible();
        await expect(page.getByText('Aucune priorité proposée pour l’instant.', { exact: false })).toBeVisible();
        await expect(page.getByText('Créez un client et une opportunité pour visualiser votre cycle commercial.')).toBeVisible();

        await navigateFromShell(page, 'CRM');
        await expect(page.getByRole('heading', { name: 'Aucun client' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Nouveau client' }).last()).toBeVisible();

        await navigateFromShell(page, 'Facturation');
        await expect(page.getByRole('heading', { name: 'Aucun devis pour l’instant' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Ouvrir le CRM' })).toBeVisible();

        await navigateFromShell(page, 'Santé');
        await expect(page.getByRole('heading', { name: 'Votre première évaluation se prépare' })).toBeVisible();

        await navigateFromShell(page, 'Advisor');
        await expect(page.getByRole('heading', { name: 'Aucune évaluation Advisor pour le moment' })).toBeVisible();

        await page.getByRole('link', { name: 'Notifications' }).click();
        await expect(page.getByRole('heading', { name: 'Aucune notification' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Retour au dashboard' })).toBeVisible();
    });
});
