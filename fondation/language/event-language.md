---
id: LANG-007
title: Event Language
status: Stable
owner: Product
version: 1.5
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

InvoiceBalanceChanged

InvoiceSettled

InvoicePaid

PaymentRecorded

CreditNoteIssued

AnalyticsSnapshotPublished

BusinessHealthAssessed

RecommendationEvaluationCompleted

RecommendationGenerated

RecommendationReaffirmed

RecommendationCompleted

RecommendationDismissed

RecommendationExpired

---

## Incorrect

CreateInvoice

InvoiceHasBeenPaid

DoPayment

PaidInvoice

InvoiceDone

`QuoteSendRequested` et `InvoiceDeliveryRequested` sont conformes : ils
décrivent une demande déjà enregistrée, pas l'exécution implicite de l'envoi.

`InvoicePaid` est réservé au règlement complet causé par un Payment ;
`InvoiceSettled` couvre aussi un solde annulé par CreditNote.

`RecommendationCompleted` signifie que l'utilisateur confirme avoir accompli
l'action. `RecommendationExecuted` est interdit tant qu'Advisor n'exécute pas
lui-même une action source vérifiable.
