---
id: BIL-RELATIONSHIPS
title: Billing Relationships
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - aggregates.md
  - invariants.md
  - integrations.md
---

# Relations

## Cardinalités internes

```text
Quote      1 -------- 0..1 DepositInvoice
Quote      1 -------- 0..1 FinalInvoice
Invoice    1 -------- 0..* Payment
Invoice    1 -------- 0..* CreditNote
CreditNote 1 -------- 1    source Invoice
```

Une Invoice autonome possède zéro `SourceQuoteId`.

---

## Client et Opportunity

Billing conserve les identifiants CRM pour la navigation, mais le document
affiche un snapshot :

```text
ClientId -------------------- current CRM relation
ClientSnapshot -------------- immutable document value
OpportunityId? -------------- commercial causality
```

Archiver ou modifier le Client ne change aucun document existant.

---

## Workspace

Tout agrégat Billing appartient à un Workspace. `IssuerSnapshot` provient de
l'identité de facturation Workspace et reste figé après finalisation ou émission.

---

## QuoteAccepted et OpportunityWon

Les faits restent distincts :

```text
QuoteAccepted
  -> orchestration
  -> CRM.WinOpportunity
  -> OpportunityWon
```

Une panne de l'orchestration ne change pas l'acceptation de la Quote.

---

## Documents et artefacts

Un PDF référence le document, son numéro, sa version immuable et une empreinte de
contenu. Il ne devient jamais la source du calcul métier.
