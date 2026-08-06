---
id: BIL-CMD-IMPORT-HISTORICAL-BILLING-HISTORY
title: ImportHistoricalBillingHistory
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-06

references:
  - README.md
  - ../aggregates.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../../../../evolution/blueprint/historical-import.md
---

# ImportHistoricalBillingHistory

## Objectif

Matérialiser des Quotes, Invoices et Payments historiques confirmés sans
rejouer leur émission, leur livraison ou leur encaissement opérationnel.

## Agrégat concerné

Nouvel agrégat `BillingHistoryImportRun`, puis Quotes et Invoices importées ;
les Payments restent contenus dans leur Invoice.

## Acteur et autorité

Membre autorisé par `billing.history.import`, après step-up lorsque la politique
de sécurité l'exige.

## Données d'entrée

```text
WorkspaceId
ImportRunId
SourceSystem
SourceExportedAt
CanonicalPackageReference
PackageHash
ClientImportManifestVersion
QuoteCount
InvoiceCount
PaymentCount
ExpectedRevision = 0
ImportHistoricalBillingHistoryRequestId
ActorContext
```

Le package 1.0 contient les formes minimales suivantes :

```text
HistoricalQuoteRecord
  ExternalId, ClientId, OriginalNumber
  Status: Sent | Accepted | Rejected | Withdrawn | Expired
  CreatedAt, SentAt, RespondedAt?, ValidUntil
  NetAmount, TaxAmount, GrossAmount, CurrencyCode

HistoricalInvoiceRecord
  ExternalId, ClientId, OriginalNumber
  DocumentStatus: Issued
  IssuedAt, DueDate
  NetAmount, TaxAmount, GrossAmount, CurrencyCode

HistoricalPaymentRecord
  ExternalId, InvoiceExternalId
  AmountReceived, AmountApplied, CurrencyCode
  ReceivedAt, Status: Active
```

Le `SettlementStatus` et l'éventuel `PaidAt` sont dérivés après application des
Payments ; ils ne sont jamais acceptés comme une vérité libre du fichier.

## Préconditions et traitement

- run Client terminé et toutes les références Client résolues ;
- package confirmé, scanné, mono-Workspace et dans sa durée de rétention ;
- numéros, dates, états, montants, devises et applications de Payment cohérents ;
- cohérence arithmétique des totaux exacts vérifiée et écarts explicitement
  refusés ;
- identité stable par système source, kind et identifiant externe ;
- traitement reprenable par checkpoint et validation finale des soldes ;
- chaque agrégat porte `HistoricalImportProvenance` et reste invisible aux
  consommateurs Analytics avant completion.

L'import ne réserve aucun numéro Atlas, n'avance aucune séquence, ne crée aucun
artefact ou preuve publique et ne demande aucune communication. Il ne produit
aucun événement opérationnel `QuoteSent`, `InvoiceIssued`, `PaymentRecorded` ou
équivalent.

## Invariants concernés

`BIL-INV-001`–`BIL-INV-009`, `BIL-INV-018`–`BIL-INV-026`,
`BIL-INV-035`–`BIL-INV-044`.

## Événements produits

- `BillingHistoryImportRequested` ;
- `BillingHistoryImportCompleted` après validation de tous les checkpoints,
  compteurs et soldes.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `ImportValidationFailed`,
`ReferenceConflict`, `CalculationConflict`, `Conflict`,
`TemporarilyUnavailable`.

## Concurrence et idempotence

`ExpectedRevision = 0` protège la création du run.
`ImportHistoricalBillingHistoryRequestId` et `PackageHash` sont obligatoires. Un
rejeu identique reprend ou retourne le run initial ; une identité de run ou
référence externe réutilisée avec un autre contenu produit `Conflict`.
