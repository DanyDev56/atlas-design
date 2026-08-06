---
id: BIL-EVENTS
title: Billing Domain Events
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - model.md
  - invariants.md
  - commands/README.md
  - integrations.md
---

# Domain Events

Les événements décrivent des faits financiers déjà commis. Leur charge utile
publique reste minimale et ne contient ni adresse complète, ni coordonnées
bancaires, ni preuve d'accès, ni contenu intégral de document.

## Enveloppe commune

| Champ | Description |
|---|---|
| `EventId` | identifiant unique de déduplication |
| `EventName` | nom canonique |
| `SchemaVersion` | version du contrat |
| `OccurredAt` | instant du fait |
| `AggregateType`, `AggregateId` | racine concernée |
| `AggregateVersion` | révision après commit |
| `WorkspaceId` | frontière d'isolation |
| `CorrelationId`, `CausationId` | chaîne du workflow |
| `ActorReference` | acteur auditable minimal |
| `Data` | fait métier minimal |

## Quote

| Événement | Producteur | Fait minimum |
|---|---|---|
| `QuoteCreated` | `CreateQuote` | Quote Draft créée. |
| `QuoteDraftUpdated` | `UpdateQuoteDraft` | Nouvelle révision du brouillon. |
| `QuoteSendRequested` | `SendQuote` | Quote finalisée et livraison demandée. |
| `QuoteSent` | `ConfirmQuoteSent` | Fournisseur ayant accepté la transmission. |
| `QuoteViewed` | `RecordQuoteViewed` | Accès public vérifié enregistré. |
| `QuoteAccepted` | `AcceptQuote` | Offre acceptée avec preuve. |
| `QuoteRejected` | `RejectQuote` | Offre rejetée avec preuve. |
| `QuoteWithdrawn` | `WithdrawQuote` | Offre retirée par l'émetteur. |
| `QuoteExpired` | `ExpireQuote` | Validité arrivée à terme. |

## Invoice

| Événement | Producteur | Fait minimum |
|---|---|---|
| `InvoiceCreated` | `CreateInvoice`, `CreateDepositInvoiceFromQuote`, `CreateFinalInvoiceFromQuote` | Invoice Draft créée avec sa provenance. |
| `InvoiceDraftUpdated` | `UpdateInvoiceDraft` | Nouvelle révision du brouillon. |
| `InvoiceDiscarded` | `DiscardInvoiceDraft` | Brouillon abandonné. |
| `InvoiceIssued` | `IssueInvoice` | Créance émise et figée. |
| `InvoiceMetadataCorrected` | `CorrectInvoiceMetadata` | Métadonnée non financière corrigée. |
| `InvoiceDeliveryRequested` | `SendInvoice` | Livraison demandée. |
| `InvoiceSent` | `ConfirmInvoiceSent` | Fournisseur ayant accepté la transmission. |
| `InvoiceViewed` | `RecordInvoiceViewed` | Accès public vérifié enregistré. |
| `InvoiceOverdue` | `MarkInvoiceOverdue` | Invoice échue avec solde positif. |
| `InvoiceReminderRequested` | `RequestInvoiceReminder` | Relance manuelle demandée. |
| `InvoiceBalanceChanged` | `RecordPayment`, `ReversePayment`, `ApplyCreditNote` | Solde modifié avec ancienne et nouvelle valeur. |
| `InvoiceSettled` | `RecordPayment`, `ApplyCreditNote` | Solde devenu nul. |
| `InvoicePaid` | `RecordPayment` | Un Payment a rendu le solde nul. |
| `InvoiceSettlementReopened` | `ReversePayment` | Une Invoice réglée retrouve un solde positif. |

`InvoicePaid` est un fait spécialisé utile aux consommateurs : toute Invoice
payée est réglée, mais une Invoice réglée par avoir n'est pas payée.

## Payment

| Événement | Producteur | Fait minimum |
|---|---|---|
| `PaymentRecorded` | `RecordPayment` | Encaissement manuel enregistré. |
| `PaymentAppliedToInvoice` | `RecordPayment` | Montant effectivement appliqué. |
| `PaymentReversed` | `ReversePayment` | Encaissement inversé avec motif. |
| `PaymentApplicationReversed` | `ReversePayment` | Application retirée du solde. |

## CreditNote

| Événement | Producteur | Fait minimum |
|---|---|---|
| `CreditNoteCreated` | `CreateCreditNote` | CreditNote Draft créée. |
| `CreditNoteDraftUpdated` | `UpdateCreditNoteDraft` | Nouvelle révision du brouillon. |
| `CreditNoteDiscarded` | `DiscardCreditNoteDraft` | Brouillon abandonné. |
| `CreditNoteIssued` | `IssueCreditNote` | CreditNote numérotée et figée. |
| `CreditNoteAppliedToInvoice` | `ApplyCreditNote` | Montant appliqué à l'Invoice source. |

## Import historique

| Événement | Producteur | Fait minimum |
|---|---|---|
| `BillingHistoryImportRequested` | `ImportHistoricalBillingHistory` | Run confirmé, hashé et accepté pour traitement. |
| `BillingHistoryImportCompleted` | `ImportHistoricalBillingHistory` | Manifest, compteurs et soldes historiques validés. |

Ces événements contiennent uniquement l'`ImportRunId`, la version du manifest,
les compteurs et le hash. Ils ne republient aucun fait opérationnel d'émission,
de livraison, d'ouverture ou de paiement.

## Publication

- état, événements et outbox sont atomiques ;
- l'ordre est garanti par agrégat via `AggregateVersion` ;
- les consommateurs dédupliquent `EventId` ;
- les retries de livraison réutilisent l'intention initiale ;
- une commande refusée ne publie aucun événement de réussite.
