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
    await expect(page.getByRole('heading', { name: 'Activité mesurée' })).toBeVisible();

    const recentInvoice = page.locator('a[href^="/app/billing/invoices/"]').first();
    await expect(recentInvoice).toBeVisible();
    await recentInvoice.click();
    await expect(page).toHaveURL(/\/app\/billing\/invoices\//);
    await expect(page.getByRole('region', { name: 'Prestations facturées' })).toBeVisible();
});

test('les paramètres exposent le profil et les membres du workspace', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'La lecture des paramètres suffit sur un viewport.');

    await navigateFromShell(page, 'Paramètres');
    await expect(page.getByRole('heading', { name: 'Paramètres' })).toBeVisible();
    await expect(page.getByLabel('Nom d’affichage')).toHaveValue('Studio Atlas Démo');
    await expect(page.getByText('Présentation Atlas', { exact: true })).toBeVisible();
    await expect(page.getByText('owner', { exact: false })).toBeVisible();
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
    await page.getByRole('button', { name: 'Consulter l’audit' }).click();
    const activityAudit = page.getByRole('region', { name: 'Audit des activités commerciales' });
    await expect(activityAudit.getByText('Corrigée', { exact: true })).toBeVisible();
    await expect(activityAudit.getByText('Retirée', { exact: true })).toBeVisible();
    await expect(activityAudit.getByText('Révisions précédentes')).toBeVisible();
    await expect(activityAudit.getByText('Compte-rendu précisé après validation des décisions avec le client.')).toBeVisible();
    await expect(activityAudit.getByText('Appel dupliqué lors de la reprise de la chronologie commerciale.')).toBeVisible();
    await page.getByRole('button', { name: 'Fermer l’audit' }).click();
    await expect(page.getByRole('button', { name: 'Retirer Camille Martin comme contact principal' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Définir Julien Morel comme contact principal' })).toBeVisible();
    await page.getByRole('button', { name: 'Archiver le client' }).click();
    const archiveClientForm = page.getByRole('form', { name: 'Archiver le client' });
    await expect(archiveClientForm.getByText(/opportunité.*encore en cours/)).toBeVisible();
    await expect(archiveClientForm.getByRole('button', { name: 'Confirmer l’archivage du client' })).toBeDisabled();
    await archiveClientForm.getByRole('button', { name: 'Annuler' }).click();
    await page.getByRole('button', { name: 'Archiver Julien Morel' }).click();
    const archiveContactForm = page.getByRole('form', { name: 'Archiver Julien Morel' });
    await expect(archiveContactForm.getByLabel('Motif d’archivage')).toBeVisible();
    await expect(archiveContactForm.getByRole('button', { name: 'Confirmer l’archivage' })).toBeVisible();
    await archiveContactForm.getByLabel('Motif d’archivage').fill('Validation du cycle de vie en démonstration');
    await archiveContactForm.getByRole('button', { name: 'Confirmer l’archivage' }).click();
    const julienCard = page.getByRole('listitem').filter({ hasText: 'Julien Morel' });
    await expect(julienCard.getByText('Archivé', { exact: true })).toBeVisible();
    await julienCard.getByRole('button', { name: 'Réactiver Julien Morel' }).click();
    await expect(page.getByRole('button', { name: 'Archiver Julien Morel' })).toBeVisible();
    await page.getByRole('button', { name: 'Modifier Camille Martin' }).click();
    const editContactForm = page.getByRole('form', { name: 'Modifier Camille Martin' });
    await expect(editContactForm.getByLabel('Nom complet')).toHaveValue('Camille Martin');
    await expect(editContactForm.getByLabel('Email (optionnel)')).toHaveValue('camille@ateliers-marais.test');
    await editContactForm.getByRole('button', { name: 'Annuler' }).click();

    await page.getByRole('button', { name: 'Nouvelle opportunité' }).click();
    await expect(page.getByLabel('Contact associé (optionnel)').locator('option:checked'))
        .toHaveText('Camille Martin — principal');

    await navigateFromShell(page, 'Facturation');
    await expect(page.getByRole('heading', { name: 'Facturation' })).toBeVisible();
    await expect(page.getByText('Maison Lumen', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Cabinet Rivoli', { exact: true }).first()).toBeVisible();
});

test('un export client peut être prévisualisé sans modifier le CRM', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'La prévisualisation complète suffit sur un viewport.');

    await navigateFromShell(page, 'CRM');
    await page.getByRole('link', { name: 'Importer un historique' }).click();
    await expect(page.getByRole('heading', { name: 'Prévisualiser des clients historiques' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Télécharger le modèle CSV' })).toBeVisible();

    const form = page.getByRole('form', { name: 'Prévisualiser un import historique de clients' });
    await form.getByLabel('Fichier clients CSV').setInputFiles({
        name: 'clients.csv',
        mimeType: 'text/csv',
        buffer: Buffer.from([
            'external_id,kind,status,display_name,email,source_created_at',
            'demo-import-001,Organization,Active,Import Démo Noroît,contact@import-demo.test,2024-01-10T09:30:00Z',
        ].join('\n')),
    });
    await form.getByRole('button', { name: 'Prévisualiser le fichier' }).click();

    const result = page.getByRole('region', { name: 'Résultat de la prévisualisation' });
    await expect(result.getByRole('heading', { name: 'Aperçu prêt pour la confirmation' })).toBeVisible();
    await expect(result.getByText('Import Démo Noroît', { exact: true })).toBeVisible();
    await expect(result.getByText('Aucun client n’a encore été importé.', { exact: false })).toBeVisible();

    await result.getByRole('button', { name: 'Confirmer et importer' }).click();
    const stepUp = page.getByRole('dialog', { name: 'Confirmer votre identité' });
    await expect(stepUp).toBeVisible();
    await stepUp.getByLabel('Mot de passe').fill(password);
    await stepUp.getByRole('button', { name: 'Continuer l’import' }).click();
    await expect(page.getByText('Import terminé avec succès.', { exact: false })).toBeVisible({ timeout: 20_000 });
});

test('les quatre fichiers Billing sont pris en compte dès leur première sélection', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'La sélection des fichiers suffit sur un viewport.');

    await navigateFromShell(page, 'Facturation');
    await page.getByRole('link', { name: 'Importer un historique' }).click();

    await page.route('**/api/workspaces/*/billing-history-imports/preview', async (route) => {
        const body = route.request().postData() ?? '';
        expect(body).toContain('filename="quotes.csv"');
        expect(body).toContain('filename="invoices.csv"');
        expect(body).toContain('filename="payments.csv"');
        expect(body).toContain('filename="credit-notes.csv"');

        await route.fulfill({
            status: 201,
            json: {
                preview_id: '00000000-0000-4000-8000-000000000201',
                schema_version: '1.0',
                source_system: 'LegacyBilling',
                source_exported_at: new Date().toISOString(),
                package_hash: 'a'.repeat(64),
                quote_count: 1,
                invoice_count: 0,
                payment_count: 0,
                credit_note_count: 0,
                validation_error_count: 0,
                valid_for_confirmation: true,
                quotes: [],
                invoices: [],
                payments: [],
                credit_notes: [],
                validation_errors: [],
                expires_at: '2099-01-01T00:00:00+00:00',
            },
        });
    });

    const form = page.getByRole('form', { name: 'Prévisualiser un import historique de facturation' });
    const files = [
        ['Fichier des devis', 'quotes.csv', 'external_id\nquote-e2e'],
        ['Fichier des factures', 'invoices.csv', 'external_id'],
        ['Fichier des paiements', 'payments.csv', 'external_id'],
        ['Fichier des avoirs', 'credit-notes.csv', 'external_id'],
    ] as const;

    for (const [label, name, content] of files) {
        await form.getByLabel(label).setInputFiles({
            name,
            mimeType: 'text/csv',
            buffer: Buffer.from(content),
        });
        await expect(form.getByLabel(label)).toHaveValue(new RegExp(`${name}$`));
    }

    await form.getByRole('button', { name: 'Prévisualiser le package' }).click();
    await expect(page.getByRole('heading', { name: 'Package prêt pour la confirmation' })).toBeVisible();
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

    await page.getByRole('link').filter({ hasText: 'Audit express à qualifier' }).click();
    await page.getByRole('button', { name: 'Marquer comme perdue' }).click();
    const lossForm = page.getByRole('form', { name: 'Marquer l’opportunité comme perdue' });
    await expect(lossForm.getByLabel('Raison de la perte').locator('option'))
        .toContainText([
            'Sélectionner une raison',
            'Budget insuffisant',
            'Calendrier ou priorité reportée',
            'Concurrent retenu',
            'Aucune décision',
            'Autre raison',
        ]);
    await expect(lossForm.getByLabel('Contexte complémentaire (optionnel)')).toBeVisible();
    await lossForm.getByRole('button', { name: 'Annuler' }).click();
    await page.getByRole('button', { name: 'Modifier l’opportunité' }).click();
    const editOpportunityForm = page.getByRole('form', { name: 'Modifier l’opportunité' });
    await expect(editOpportunityForm.getByLabel('Titre')).toHaveValue('Audit express à qualifier');
    const contactSelect = editOpportunityForm.getByLabel('Contact associé (optionnel)');
    await expect(contactSelect.getByRole('option')).toHaveCount(3);
    await expect(contactSelect.getByRole('option', { name: 'Aucun contact associé' })).toBeAttached();
    await expect(contactSelect.getByRole('option', { name: 'Camille Martin — principal' })).toBeAttached();
    await expect(contactSelect.getByRole('option', { name: 'Julien Morel' })).toBeAttached();
    await editOpportunityForm.getByRole('button', { name: 'Annuler' }).click();
    await page.goBack();
    await expect(page.getByRole('heading', { name: 'Les Ateliers du Marais' })).toBeVisible();

    await page.getByRole('link').filter({ hasText: 'Refonte identité visuelle' }).click();
    await expect(page.getByRole('heading', { name: 'Refonte identité visuelle' })).toBeVisible();
    await page.getByRole('button', { name: 'Marquer comme gagnée' }).click();
    const winConfirmation = page.getByRole('region', { name: 'Marquer l’opportunité comme gagnée' });
    await expect(winConfirmation.getByText('La clôture est définitive', { exact: false })).toBeVisible();
    await expect(winConfirmation.getByText('ne crée ni devis ni facture', { exact: false })).toBeVisible();
    await expect(winConfirmation.getByRole('button', { name: 'Confirmer le gain' })).toBeVisible();
    await winConfirmation.getByRole('button', { name: 'Annuler' }).click();
    await page.getByRole('link', { name: 'Vérifier et envoyer' }).click();
    await expect(page.getByRole('button', { name: 'Envoyer au client' })).toBeVisible();
});

test('un client sans opportunité active peut être archivé puis réactivé', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Le cycle métier complet suffit sur un viewport.');

    await navigateFromShell(page, 'CRM');
    await page.getByRole('link').filter({ hasText: 'Horizon Digital' }).click();
    await expect(page.getByRole('heading', { name: 'Horizon Digital' })).toBeVisible();
    const activityTimeline = page.getByRole('region', { name: 'Chronologie commerciale' });
    await expect(activityTimeline.getByText('Confirmation reçue : la migration cloud est terminée et la facture a été réglée.')).toBeVisible();
    await expect(activityTimeline.getByText('Avec Sarah Benali · Opportunité : Migration cloud')).toBeVisible();
    const seededActivity = activityTimeline.getByRole('listitem')
        .filter({ hasText: 'Confirmation reçue : la migration cloud est terminée et la facture a été réglée.' });
    await seededActivity.getByRole('button', { name: 'Corriger' }).click();
    const correctionForm = seededActivity.getByRole('form', { name: 'Corriger une activité commerciale' });
    await expect(correctionForm.getByLabel('Type corrigé')).toHaveValue('Email');
    await expect(correctionForm.getByLabel('Résumé corrigé')).toHaveValue(
        'Confirmation reçue : la migration cloud est terminée et la facture a été réglée.',
    );
    await expect(correctionForm.getByLabel('Motif de la correction')).toBeVisible();
    await correctionForm.getByRole('button', { name: 'Annuler' }).click();
    await seededActivity.getByRole('button', { name: 'Retirer' }).click();
    const removalForm = seededActivity.getByRole('form', { name: 'Retirer une activité commerciale' });
    await expect(removalForm.getByText('Le retrait est définitif', { exact: false })).toBeVisible();
    await expect(removalForm.getByLabel('Motif du retrait')).toBeVisible();
    await expect(removalForm.getByRole('button', { name: 'Confirmer le retrait' })).toBeVisible();
    await removalForm.getByRole('button', { name: 'Annuler' }).click();
    await activityTimeline.getByRole('button', { name: 'Ajouter une activité' }).click();
    const activityForm = page.getByRole('form', { name: 'Ajouter une activité commerciale' });
    await expect(activityForm.getByLabel('Type d’activité')).toHaveValue('Note');
    await expect(activityForm.getByLabel('Contact concerné (optionnel)').getByRole('option')).toContainText([
        'Aucun contact',
        'Sarah Benali',
    ]);
    await activityForm.getByRole('button', { name: 'Annuler' }).click();

    await page.getByRole('button', { name: 'Modifier les informations' }).click();
    let profileForm = page.getByRole('form', { name: 'Modifier les informations du client' });
    await expect(profileForm.getByLabel('Nom affiché')).toHaveValue('Horizon Digital');
    await expect(profileForm.getByLabel('Email (optionnel)')).toHaveValue('projets@horizon-digital.test');
    await profileForm.getByLabel('Raison sociale (optionnel)').fill('Horizon Digital SAS');
    await profileForm.getByRole('button', { name: 'Enregistrer les informations' }).click();
    await expect(page.getByText('Horizon Digital SAS', { exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Modifier les informations' }).click();
    profileForm = page.getByRole('form', { name: 'Modifier les informations du client' });
    await profileForm.getByLabel('Raison sociale (optionnel)').fill('');
    await profileForm.getByRole('button', { name: 'Enregistrer les informations' }).click();
    await expect(page.getByText('Les informations de « Horizon Digital » sont enregistrées.')).toBeVisible();
    await expect(page.getByText('Horizon Digital SAS', { exact: true })).toHaveCount(0);

    await page.getByRole('button', { name: 'Modifier la facturation' }).click();
    let billingForm = page.getByRole('form', { name: 'Modifier les informations de facturation' });
    await billingForm.getByLabel('Nom de facturation (optionnel)').fill('Horizon Digital SAS');
    await billingForm.getByLabel('Email de facturation (optionnel)').fill('facturation@horizon-digital.test');
    await billingForm.getByLabel('Adresse (optionnel)', { exact: true }).fill('14 rue des Entrepreneurs');
    await billingForm.getByLabel('Code postal (optionnel)').fill('44000');
    await billingForm.getByLabel('Ville (optionnel)').fill('Nantes');
    await billingForm.getByLabel('Code pays (optionnel)').fill('FR');
    await billingForm.getByRole('button', { name: 'Ajouter un identifiant', exact: true }).click();
    await billingForm.getByLabel('Type 1').fill('SIRET');
    await billingForm.getByLabel('Valeur 1').fill('123 456 789 00012');
    await billingForm.getByRole('button', { name: 'Enregistrer la facturation' }).click();
    await expect(page.getByText('Les informations de facturation sont enregistrées pour les prochains documents.')).toBeVisible();
    await expect(page.getByText('Horizon Digital SAS', { exact: true })).toBeVisible();
    await expect(page.getByText('SIRET : 123 456 789 00012', { exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Modifier la facturation' }).click();
    billingForm = page.getByRole('form', { name: 'Modifier les informations de facturation' });
    await billingForm.getByLabel('Nom de facturation (optionnel)').fill('');
    await billingForm.getByLabel('Email de facturation (optionnel)').fill('');
    await billingForm.getByLabel('Adresse (optionnel)', { exact: true }).fill('');
    await billingForm.getByLabel('Code postal (optionnel)').fill('');
    await billingForm.getByLabel('Ville (optionnel)').fill('');
    await billingForm.getByLabel('Code pays (optionnel)').fill('');
    await billingForm.getByRole('button', { name: 'Retirer l’identifiant d’entreprise 1' }).click();
    await billingForm.getByRole('button', { name: 'Enregistrer la facturation' }).click();
    await expect(page.getByText('Aucune information administrative n’est encore renseignée.')).toBeVisible();

    await page.getByRole('button', { name: 'Archiver le client' }).click();
    const archiveForm = page.getByRole('form', { name: 'Archiver le client' });
    await archiveForm.getByLabel('Motif d’archivage').fill('Fin du dossier de démonstration');
    await archiveForm.getByRole('button', { name: 'Confirmer l’archivage du client' }).click();
    await expect(page.getByText('Client conservé dans l’historique')).toBeVisible();

    await page.getByRole('button', { name: 'Réactiver le client' }).click();
    const reactivateGroup = page.getByRole('group', { name: 'Confirmer la réactivation du client' });
    await expect(reactivateGroup.getByText('Ses contacts archivés resteront archivés.', { exact: false })).toBeVisible();
    await reactivateGroup.getByRole('button', { name: 'Confirmer la réactivation' }).click();
    await expect(page.getByText('Le client « Horizon Digital » est de nouveau actif.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Archiver le client' })).toBeVisible();
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
        await expect(page.getByRole('heading', { name: 'Activité mesurée' })).toBeVisible();
        await expect(page.getByText('Aucune génération Analytics n’est encore publiée', { exact: false })).toBeVisible();

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
