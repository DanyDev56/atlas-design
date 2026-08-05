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

Exemple :

OpportunityCreated
        │
        ▼
QuoteCreated
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
InvoicePaid
        │
        ▼
BusinessHealthUpdated
        │
        ▼
RecommendationGenerated
        │
        ▼
NotificationSent

---

Chaque événement possède :

- un producteur ;
- zéro ou plusieurs consommateurs.

Les producteurs ignorent toujours qui consomme leurs événements.
