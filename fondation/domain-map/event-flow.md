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
BusinessHealthAssessed
        │
        ▼
AdvisorOverviewChanged
        │
        ▼
Notification plan
        ├──► NotificationCreated
        │          └──► NotificationDeliveryRequested
        │                     └──► NotificationDeliveryAccepted
        │                                └──► NotificationDeliveryConfirmed
        └──► NotificationPlanCompleted
```

`InvoicePaid` n'existe que si un Payment rend le solde nul. Une CreditNote peut
produire `InvoiceSettled` sans produire `InvoicePaid`.

NotificationCreated et les faits de NotificationDelivery sont optionnels : audience vide,
source inéligible, contenu inchangé ou canaux supprimés terminent néanmoins un
NotificationPlan. Accepted signifie prise en charge fournisseur ; seule
NotificationDeliveryConfirmed prouve Delivered.

---

## Cycle d'une Recommendation

```text
RecommendationGenerated
        ├──► RecommendationReaffirmed ──► Generated
        ├──► RecommendationCompleted ──► AdvisorOverviewChanged
        ├──► RecommendationDismissed ──► AdvisorOverviewChanged
        └──► RecommendationExpired ──► AdvisorOverviewChanged
```

Reaffirmed conserve l'état Generated. Completed, Dismissed et Expired sont
terminaux et mutuellement exclusifs. Chaque mutation terminale reconstruit
l'overview avant toute planification Notifications.

---

## Cycle d'une Notification

```text
NotificationCreated
        ├──► NotificationMarkedRead ◄──► NotificationMarkedUnread
        ├──► NotificationResolved
        ├──► NotificationSuperseded
        └──► NotificationExpired
```

Read/Unread est indépendant du statut de pertinence. Resolved, Superseded et
Expired sont terminaux ; une nouvelle priorité crée une nouvelle Notification.

---

Chaque événement possède :

- un producteur ;
- zéro ou plusieurs consommateurs.

Les producteurs ignorent toujours qui consomme leurs événements.
