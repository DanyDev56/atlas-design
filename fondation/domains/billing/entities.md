---
id: BIL-ENTITIES
title: Billing Entities
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - aggregates.md
  - value-objects.md
  - invariants.md
---

# Entités

## Quote

Proposition commerciale chiffrée adressée à un Client.

| Attribut | Rôle |
|---|---|
| `QuoteId`, `WorkspaceId` | identité et isolation |
| `Status` | `Draft`, `Sending`, `Sent`, `Accepted`, `Rejected`, `Withdrawn`, `Expired` |
| `QuoteNumber?` | numéro alloué avant envoi |
| `Lines`, `Totals`, `Currency` | contenu financier |
| snapshots | Client, Opportunity éventuelle et émetteur |
| `ValidUntil` | échéance de réponse |
| `DepositPolicy?` | acompte simple prévu |
| `InvoicingSummary` | facturation issue de la Quote |
| `PublicAccess` | empreinte et version de preuve |

---

## Invoice

Document émis représentant une créance.

| Attribut | Rôle |
|---|---|
| `InvoiceId`, `WorkspaceId` | identité et isolation |
| `Kind` | `Standard`, `Deposit`, `Final` |
| `DocumentStatus` | `Draft`, `Issued`, `Discarded` |
| `InvoiceNumber?` | numéro alloué à l'émission |
| `SourceQuoteId?` | origine commerciale |
| `Lines`, `Totals`, `Currency` | contenu financier |
| `IssuedAt?`, `DueDate?` | portée temporelle |
| `Payments` | règlements contenus |
| `CreditApplications` | applications d'avoirs |
| `OutstandingBalance` | solde courant contrôlé |
| `SettlementStatus` | `Outstanding`, `PartiallySettled`, `Settled` |
| `Overdue` | indicateur dérivé de l'échéance et du solde |
| `DeliveryState`, `PublicAccess` | projection de livraison et preuve de lecture |
| snapshots | Client et émetteur figés |

---

## Payment

Entité contenue dans une Invoice et représentant un règlement enregistré.

| Attribut | Rôle |
|---|---|
| `PaymentId` | identité stable |
| `Status` | `Recorded` ou `Reversed` |
| `AmountReceived` | montant observé |
| `AmountApplied` | part réduisant la créance |
| `UnappliedAmount` | éventuel trop-perçu |
| `OverpaymentDisposition?` | `RefundDue` ou `ClientCredit` |
| `ReceivedAt`, `Method`, `Reference?` | preuve métier minimale |
| reversal context | raison, acteur et instant |

Un Payment n'est ni un mouvement bancaire importé, ni une écriture comptable.

---

## CreditNote

Document correctif d'une Invoice émise.

| Attribut | Rôle |
|---|---|
| `CreditNoteId`, `WorkspaceId` | identité et isolation |
| `InvoiceId` | Invoice corrigée, immuable |
| `Status` | `Draft`, `Issued`, `Applied`, `Discarded` |
| `CreditNoteNumber?` | numéro alloué à l'émission |
| `Lines`, `Totals`, `Currency` | correction chiffrée |
| `AmountApplied` | part réduisant le solde |
| `UnappliedAmount` | montant à rembourser ou créditer |
| `RemainderDisposition?` | `RefundDue` ou `ClientCredit` si reliquat |
| snapshots | copies de l'Invoice source |

---

## DocumentNumberSequence

Entité système allouant des numéros uniques par Workspace, type, série et
période. Chaque réservation est atomique, monotone et auditée.
