# Flux d'événements

Exemple :

OpportunityWon

↓

QuoteCreated

↓

QuoteAccepted

↓

InvoiceIssued

↓

PaymentReceived

↓

BusinessHealthUpdated

↓

RecommendationGenerated

↓

NotificationSent

---

Chaque événement possède :

- un producteur ;
- zéro ou plusieurs consommateurs.

Les producteurs ignorent toujours qui consomme leurs événements.