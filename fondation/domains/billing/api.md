---
id: BIL-PUBLIC-CONTRACT
title: Billing Public Contract
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-06

references:
  - scope.md
  - invariants.md
  - events.md
  - permissions.md
  - commands/README.md
---

# Contrat public Billing

Le contrat décrit des intentions et lectures indépendantes de HTTP, RPC ou UI.
Les signatures abrégées renvoient aux fiches de commandes pour les preuves,
révisions et RequestIds complets.

## Commandes Quote

```text
createQuote(workspaceId, clientId, opportunityId?, draft, requestId)
updateQuoteDraft(workspaceId, quoteId, changes, expectedRevision, requestId)
sendQuote(workspaceId, quoteId, delivery, expectedRevision, requestId)
confirmQuoteSent(workspaceId, quoteId, deliveryReceipt, expectedRevision, requestId)
recordQuoteViewed(workspaceId, quoteId, publicProof, expectedRevision, requestId)
acceptQuote(workspaceId, quoteId, publicProof, acceptance, expectedRevision, requestId)
rejectQuote(workspaceId, quoteId, publicProof, rejection, expectedRevision, requestId)
withdrawQuote(workspaceId, quoteId, reason, expectedRevision, requestId)
expireQuote(workspaceId, quoteId, clockProof, expectedRevision, requestId)
```

## Commandes Invoice et Payment

```text
createInvoice(workspaceId, clientId, draft, requestId)
createDepositInvoiceFromQuote(workspaceId, quoteId, depositPolicy,
                              expectedQuoteRevision, requestId)
createFinalInvoiceFromQuote(workspaceId, quoteId, expectedQuoteRevision, requestId)
updateInvoiceDraft(workspaceId, invoiceId, changes, expectedRevision, requestId)
discardInvoiceDraft(workspaceId, invoiceId, reason, expectedRevision, requestId)
issueInvoice(workspaceId, invoiceId, issueDate, expectedRevision, requestId)
correctInvoiceMetadata(workspaceId, invoiceId, correction, expectedRevision, requestId)
sendInvoice(workspaceId, invoiceId, delivery, expectedRevision, requestId)
confirmInvoiceSent(workspaceId, invoiceId, deliveryReceipt, expectedRevision, requestId)
recordInvoiceViewed(workspaceId, invoiceId, publicProof, expectedRevision, requestId)
markInvoiceOverdue(workspaceId, invoiceId, clockProof, expectedRevision, requestId)
requestInvoiceReminder(workspaceId, invoiceId, delivery, expectedRevision, requestId)
recordPayment(workspaceId, invoiceId, payment, expectedRevision, requestId)
reversePayment(workspaceId, invoiceId, paymentId, reason, expectedRevision, requestId)
importHistoricalBillingHistory(workspaceId, importManifest, expectedRevision, requestId)
```

## Commandes CreditNote

```text
createCreditNote(workspaceId, invoiceId, draft, requestId)
updateCreditNoteDraft(workspaceId, creditNoteId, changes, expectedRevision, requestId)
discardCreditNoteDraft(workspaceId, creditNoteId, reason, expectedRevision, requestId)
issueCreditNote(workspaceId, creditNoteId, issueDate, expectedRevision, requestId)
applyCreditNote(workspaceId, creditNoteId, invoiceId, amount, remainderDisposition?,
                expectedCreditNoteRevision, expectedInvoiceRevision, requestId)
```

## Lectures de membre

```text
getQuote(workspaceId, quoteId)
listQuotes(workspaceId, clientId?, status?, cursor?)
getInvoice(workspaceId, invoiceId)
listInvoices(workspaceId, clientId?, documentStatus?, settlementStatus?, overdue?, cursor?)
getInvoiceBalance(workspaceId, invoiceId)
listInvoicePayments(workspaceId, invoiceId)
getCreditNote(workspaceId, creditNoteId)
listCreditNotes(workspaceId, invoiceId?, status?, cursor?)
getDocumentArtifact(workspaceId, documentType, documentId, artifactVersion)
getBillingHistoryImport(workspaceId, importRunId)
```

Les permissions `read` correspondantes s'appliquent. Les listes sont paginées
et les projections peuvent être éventuellement cohérentes sans modifier les
agrégats.

## Lectures publiques

```text
getPublicQuote(publicDocumentProof)
getPublicInvoice(publicDocumentProof)
getPublicDocumentArtifact(publicDocumentProof, artifactVersion)
```

Une preuve donne uniquement accès au document, à la capacité et à la durée
prévus. Aucun échec ne confirme l'existence d'une autre ressource.

## Contrats fournis à Analytics

```text
getQuoteAnalyticsFact(workspaceId, quoteId, aggregateVersion)
→
WorkspaceId, QuoteId, AggregateVersion, ClientId, OpportunityId?, Status,
GrossAmount, CurrencyCode, CreatedAt, SentAt?, RespondedAt?, TerminalAt?,
ValidUntil, FactHash

getInvoiceAnalyticsFact(workspaceId, invoiceId, aggregateVersion)
→
WorkspaceId, InvoiceId, AggregateVersion, ClientId, Kind, DocumentStatus,
GrossAmount, CurrencyCode, IssuedAt?, DueDate?, OutstandingBalance,
SettlementStatus, PaidAt?, FactHash

getPaymentAnalyticsFact(workspaceId, invoiceId, paymentId, aggregateVersion)
→
WorkspaceId, InvoiceId, PaymentId, AggregateVersion, ClientId, Status,
AmountApplied, CurrencyCode, ReceivedAt, ReversedAt?, FactHash

getCreditNoteAnalyticsFact(workspaceId, creditNoteId, aggregateVersion)
→
WorkspaceId, CreditNoteId, AggregateVersion, InvoiceId, ClientId, Status,
GrossAmount, CurrencyCode, IssuedAt?, FactHash
```

Chaque fait contient uniquement les identifiants, statuts, montants, devise et
instants nécessaires à Analytics, avec `ClientId`, `AggregateVersion` et
`FactHash`. La lecture exige `billing.analytics-facts.read` et exclut lignes,
snapshots, adresses, références de paiement et preuves publiques.

`PaidAt` existe seulement lorsqu'un Payment a rendu le solde nul à cette
révision. Il redevient absent après `InvoiceSettlementReopened` jusqu'à un
nouveau règlement complet par Payment.

Après `BillingHistoryImportCompleted`, Analytics peut relire le manifest minimal :

```text
getBillingHistoryImportManifest(workspaceId, importRunId, manifestVersion)
→
WorkspaceId
ImportRunId
ManifestVersion
PackageHash
Records[] { RecordKind, AggregateId, AggregateVersion, SourceOccurredAt, FactHash }
```

La lecture exige `billing.analytics-facts.read`. Elle exclut le package brut,
les numéros de document, les références de paiement et les identifiants externes.

## Erreurs publiques

| Catégorie | Sens |
|---|---|
| `InvalidInput` | valeur syntaxiquement invalide |
| `Unauthenticated` | principal ou preuve absent/invalide |
| `Unauthorized` | capacité insuffisante |
| `NotFound` | ressource absente ou masquée |
| `InvalidState` | cycle de vie incompatible |
| `InvariantViolation` | règle absolue menacée |
| `ReferenceConflict` | Client, Opportunity, Quote ou Invoice incompatible |
| `CalculationConflict` | totaux ou politique de calcul incompatibles |
| `BalanceConflict` | application supérieure au solde ou état de règlement obsolète |
| `NumberingUnavailable` | allocation de numéro impossible |
| `ImportValidationFailed` | package, mapping ou soldes historiques incohérents |
| `Conflict` | révision ou idempotence incompatible |
| `TemporarilyUnavailable` | dépendance nécessaire indisponible |

## Versioning

Les consommateurs tolèrent les champs inconnus. Tout changement de sens, de
cycle, d'enum, de permission ou d'événement publié exige une version explicite.
