---
id: BIL-COMMANDS
title: Billing Commands
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - ../aggregates.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# Commands

Une commande exprime une intention métier unique et produit seulement les faits
commis dans Billing.

## Catalogue

### Quote

- [`CreateQuote`](CreateQuote.md)
- [`UpdateQuoteDraft`](UpdateQuoteDraft.md)
- [`SendQuote`](SendQuote.md)
- [`ConfirmQuoteSent`](ConfirmQuoteSent.md)
- [`RecordQuoteViewed`](RecordQuoteViewed.md)
- [`AcceptQuote`](AcceptQuote.md)
- [`RejectQuote`](RejectQuote.md)
- [`WithdrawQuote`](WithdrawQuote.md)
- [`ExpireQuote`](ExpireQuote.md)

### Invoice

- [`CreateInvoice`](CreateInvoice.md)
- [`CreateDepositInvoiceFromQuote`](CreateDepositInvoiceFromQuote.md)
- [`CreateFinalInvoiceFromQuote`](CreateFinalInvoiceFromQuote.md)
- [`UpdateInvoiceDraft`](UpdateInvoiceDraft.md)
- [`DiscardInvoiceDraft`](DiscardInvoiceDraft.md)
- [`IssueInvoice`](IssueInvoice.md)
- [`CorrectInvoiceMetadata`](CorrectInvoiceMetadata.md)
- [`SendInvoice`](SendInvoice.md)
- [`ConfirmInvoiceSent`](ConfirmInvoiceSent.md)
- [`RecordInvoiceViewed`](RecordInvoiceViewed.md)
- [`MarkInvoiceOverdue`](MarkInvoiceOverdue.md)
- [`RequestInvoiceReminder`](RequestInvoiceReminder.md)

### Payment

- [`RecordPayment`](RecordPayment.md)
- [`ReversePayment`](ReversePayment.md)

### CreditNote

- [`CreateCreditNote`](CreateCreditNote.md)
- [`UpdateCreditNoteDraft`](UpdateCreditNoteDraft.md)
- [`DiscardCreditNoteDraft`](DiscardCreditNoteDraft.md)
- [`IssueCreditNote`](IssueCreditNote.md)
- [`ApplyCreditNote`](ApplyCreditNote.md)

### Historique initial

- [`ImportHistoricalBillingHistory`](ImportHistoricalBillingHistory.md)

## Conventions communes

- toute commande porte `WorkspaceId` et un RequestId spécialisé ;
- toute mutation existante compare `ExpectedRevision` ;
- une opération multi-agrégats compare chacune de leurs révisions ;
- une intention humaine exige la permission canonique du même Workspace ;
- une autorité publique ou système est bornée et auditée ;
- état, événements, numéro réservé et outbox sont commis atomiquement ;
- un refus ne produit aucun événement de réussite.

## Évolution

Une nouvelle commande doit représenter une intention absente du catalogue et
documenter agrégat, autorité, données, invariants, événements, concurrence,
idempotence et erreurs.
