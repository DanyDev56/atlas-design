---
id: ANL-INTEGRATIONS
title: Analytics Integrations
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - api.md
  - events.md
  - workflows.md
  - ../crm/api.md
  - ../crm/events.md
  - ../billing/api.md
  - ../billing/events.md
  - ../workspace/api.md
  - ../identity/api.md
---

# Intégrations

## Identity

Les lectures humaines demandent `analytics.metrics.read`. Les processeurs et
Business Health utilisent exclusivement les capacités SystemActorOnly prévues.
Analytics ne lit ni Membership, ni Role, ni Session dans le stockage Identity.

## Workspace

Analytics consomme :

```text
getWorkspaceAccessContext(workspaceId)
getWorkspacePreferences(workspaceId)
```

Les préférences fournissent le `ReportingCalendar`. Une modification de fuseau
ou de calendrier déclenche une nouvelle génération, jamais une réécriture des
faits ou snapshots publiés.

## CRM

Événements supportés :

```text
OpportunityCreated
OpportunityUpdated
OpportunityQualified
OpportunityWon
OpportunityLost
```

Pour chaque signal, Analytics lit avec `crm.analytics-facts.read` :

```text
getOpportunityAnalyticsFact(workspaceId, opportunityId, aggregateVersion)
```

Le fait contient identifiants, statut, estimation et instants nécessaires, sans
profil Client, description ou `NextAction`.

## Billing

Événements supportés :

```text
QuoteSent
QuoteAccepted
QuoteRejected
QuoteWithdrawn
QuoteExpired

InvoiceIssued
InvoiceBalanceChanged

PaymentRecorded
PaymentReversed
CreditNoteIssued
```

Analytics lit avec `billing.analytics-facts.read` :

```text
getQuoteAnalyticsFact(workspaceId, quoteId, aggregateVersion)
getInvoiceAnalyticsFact(workspaceId, invoiceId, aggregateVersion)
getPaymentAnalyticsFact(workspaceId, invoiceId, paymentId, aggregateVersion)
getCreditNoteAnalyticsFact(workspaceId, creditNoteId, aggregateVersion)
```

Ces contrats exposent montants, devises, statuts, ClientId et instants minimaux,
jamais snapshots de document, adresses, lignes, références de paiement ou
preuves publiques.

## Business Health

Business Health consomme `AnalyticsSnapshotPublished`, puis relit le snapshot
exact avec `analytics.snapshots.consume`. Il ne lit pas les séries privées et ne
redéfinit pas les formules.

## Advisor et Notifications

Advisor consomme prioritairement Business Health. Une explication peut référencer
une métrique publique, mais Advisor ne dépend pas du ledger de faits Analytics.
Notifications ne reçoit pas les variations ordinaires de métriques.

## Garanties

- aucune base de données partagée ;
- lectures de faits versionnées, bornées et idempotentes ;
- timeouts, retries avec backoff et dead-letter observable ;
- checkpoints durables avant progression du watermark ;
- rebuild possible à partir des domaines autoritaires ;
- aucune indisponibilité ne transforme une donnée absente en zéro.
