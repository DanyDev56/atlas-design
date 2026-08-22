---
id: BIL-WORKFLOWS
title: Billing Workflows
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - model.md
  - invariants.md
  - commands/README.md
  - events.md
  - integrations.md
---

# Workflows

## Finaliser et envoyer une Quote

1. `CreateQuote` copie les contextes CRM et Workspace dans un brouillon.
2. `UpdateQuoteDraft` affine lignes, taxes, validité et conditions.
3. `SendQuote` relit les contextes, calcule les totaux, alloue le numéro, fige
   les snapshots, crée la preuve publique et publie `QuoteSendRequested`.
4. Communication traite l'outbox ; `ConfirmQuoteSent` publie `QuoteSent` après
   acceptation de la transmission par le fournisseur.
5. Un accès vérifié peut produire `QuoteViewed`.
6. Le destinataire exécute exactement une fois `AcceptQuote` ou `RejectQuote`.

Un échec de livraison ne remet pas la Quote en Draft. L'outbox retente la même
intention ; l'émetteur peut la retirer explicitement.

## Importer l'historique initial

1. l'adaptateur prépare un package canonique et un aperçu sans mutation ;
2. le manifest Client CRM terminé résout chaque contrepartie ;
3. `ImportHistoricalBillingHistory` confirme hash, compteurs et mapping ;
4. le run matérialise Quotes, Invoices, Payments puis CreditNotes par
   checkpoints ;
5. Billing recalcule les totaux et soldes, et bloque tout écart ;
6. la completion rend les agrégats visibles et publie
   `BillingHistoryImportCompleted` ;
7. Analytics reconstruit ensuite une génération contrôlée.

Ce workflow ne passe jamais par les commandes de création, d'envoi, d'émission,
d'encaissement ou d'application d'avoir. Une relance ultérieure reste une
nouvelle intention humaine après revalidation de l'adresse et de l'autorisation.

## Quote acceptée vers Opportunity et Invoices

`QuoteAccepted` déclenche deux suites indépendantes :

- l'orchestrateur CRM tente `WinOpportunity` de façon idempotente ;
- Billing autorise une `DepositInvoice` optionnelle puis une `FinalInvoice`.

La création depuis Quote réserve atomiquement sa place dans
`InvoicingSummary`. La finale facture le reliquat non déjà alloué.

## Émettre et livrer une Invoice

1. création d'un Draft autonome ou depuis Quote ;
2. contrôle et modification éventuelle du brouillon ;
3. `IssueInvoice` fige snapshots, échéance et totaux puis alloue le numéro ;
4. le rendu produit un PDF lié à la version émise ;
5. `SendInvoice` demande la livraison et `ConfirmInvoiceSent` matérialise la
   remise au fournisseur ;
6. un accès public vérifié peut produire `InvoiceViewed`.

Après émission, une correction financière exige une CreditNote. Seules les
métadonnées explicitement non financières peuvent être corrigées en place avec
une trace.

## Enregistrer un Payment

1. l'utilisateur saisit le montant reçu, la date, la référence et le mode ;
2. Billing applique au plus le solde courant ;
3. un dépassement reste non appliqué avec `RefundDue` ou `ClientCredit` ;
4. le solde et `SettlementStatus` sont recalculés atomiquement ;
5. si le solde devient nul, `InvoiceSettled` et `InvoicePaid` sont publiés.

Les règlements partiels répètent ce workflow. Un Payment erroné est entièrement
inversé ; si le solde nul redevient positif, `InvoiceSettlementReopened` est
publié.

## Corriger par CreditNote

1. `CreateCreditNote` référence une Invoice émise ;
2. le Draft décrit les lignes corrigées ;
3. `IssueCreditNote` alloue un numéro et fige le document ;
4. `ApplyCreditNote` diminue le solde sans le rendre négatif ;
5. un reliquat non applicable reste porté par la CreditNote.

## Échéance et relance

Le scheduler propose `MarkInvoiceOverdue` après la date d'échéance. Billing ne
publie `InvoiceOverdue` que si le solde est positif et le passage n'a pas déjà
été matérialisé. Un membre peut ensuite demander une relance manuelle ; la
livraison reste asynchrone et idempotente.

## Workspace restreint ou fermé

Les mutations ordinaires sont refusées selon la politique Workspace. Les
documents, règlements, preuves d'audit et obligations de rétention restent
intacts. Les lectures ou corrections légalement requises nécessitent une
capacité de gouvernance explicite, hors rôle métier ordinaire.
