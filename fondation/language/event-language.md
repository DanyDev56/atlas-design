---
id: LANG-007
title: Event Language
status: Stable
owner: Product
version: 1.1
last_updated: 2026-08-05
---

# Convention des événements

Les événements représentent des faits métier déjà réalisés.

Ils utilisent toujours :

NomEntité + VerbeAuPassé

---

## Correct

WorkspaceCreated

ClientCreated

ClientArchived

OpportunityQualified

OpportunityWon

ActivityRecorded

QuoteSent

QuoteAccepted

InvoiceIssued

PaymentRecorded

RecommendationGenerated

RecommendationDismissed

---

## Incorrect

CreateInvoice

InvoiceHasBeenPaid

DoPayment

PaidInvoice

InvoiceDone
