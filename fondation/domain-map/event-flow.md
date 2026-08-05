# Flux d'événements

## Fondation d'un Workspace

```text
WorkspaceCreated
        │
        ▼
Bootstrap orchestrator
        │
        ├──► Identity commands
        │
        ▼
WorkspaceActivated
        │
        ▼
WorkspaceAccessStateChanged
        ├──► Identity authorization
        └──► domain access projections
```

---

## Cycle commercial

```text
OpportunityCreated
        │
        ▼
OpportunityQualified
        │
        ▼
QuoteCreated
        │
        ▼
QuoteSendRequested
        │
        ▼
QuoteSent
        │
        ▼
QuoteAccepted
        │
        ├──► OpportunityWon
        │
        ▼
InvoiceCreated
        │
        ▼
InvoiceIssued
        │
        ▼
PaymentRecorded
        │
        ▼
InvoiceBalanceChanged
        │
        ├──► InvoicePaid
        │
        ▼
InvoiceSettled
        │
        ▼
AnalyticsSnapshotPublished
        │
        ▼
BusinessHealthUpdated
        │
        ▼
RecommendationGenerated
        │
        ▼
NotificationSent
```

`InvoicePaid` n'existe que si un Payment rend le solde nul. Une CreditNote peut
produire `InvoiceSettled` sans produire `InvoicePaid`.

---

Chaque événement possède :

- un producteur ;
- zéro ou plusieurs consommateurs.

Les producteurs ignorent toujours qui consomme leurs événements.
