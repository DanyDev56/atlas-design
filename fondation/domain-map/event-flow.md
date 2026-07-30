# Flux d'événements

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
PaymentReceived
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