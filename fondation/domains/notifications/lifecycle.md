---
id: NTF-LIFECYCLE
title: Notification Lifecycles
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - notification-policy.md
  - model.md
  - invariants.md
  - commands/README.md
  - processors/README.md
  - events.md
---

# Cycles de vie

## NotificationStatus

```text
Active
  ├──► Resolved
  ├──► Superseded
  └──► Expired
```

- `Resolved` signifie que la Recommendation liée a été Completed ou Dismissed,
  ou que l'overview ne contient plus de priorité ;
- `Superseded` signifie qu'une nouvelle PrimaryRecommendation a remplacé le
  contenu du même thread ;
- `Expired` signifie qu'un AdvisorOverviewChanged de kind
  RecommendationExpired ou que DisplayUntil a mis fin à sa pertinence.

Ces statuts sont terminaux. Une nouvelle priorité crée une nouvelle
Notification.

## NotificationReadState

```text
Unread <──> Read
NotApplicable
```

ReadState est orthogonal à NotificationStatus. Une Notification InApp peut être
marquée read ou unread tant qu'elle reste accessible dans l'historique.
`NotApplicable` est réservé à une Notification sans canal InApp.

Superseded, Resolved ou Expired ne signifie pas automatiquement Read.

## DeliveryStatus

```text
Pending
  ├──► Dispatching ──► Accepted ──► Delivered
  │         │              └──────► Failed
  │         ├──► Pending
  │         └──► Failed
  ├──► Suppressed
  └──► Cancelled
```

- `Pending` attend un dispatch ;
- `Dispatching` possède un lease borné ;
- `Accepted` signifie que le fournisseur a pris en charge la demande ;
- `Delivered` exige une confirmation authentique ;
- `Failed` signifie échec permanent ou tentatives épuisées ;
- `Suppressed` résulte d'une règle avant effet externe ;
- `Cancelled` résulte d'une Notification devenue non pertinente avant dispatch.

Delivered, Failed, Suppressed et Cancelled sont terminaux. Accepted peut encore
devenir Failed après un bounce ou une plainte fournisseur.

## DeliveryAttempt

Chaque tentative est immuable. Un échec transient crée une tentative suivante
avec la même ProviderIdempotencyKey et un `NextAttemptAt` calculé par la
BackoffPolicy. Un timeout ambigu n'autorise jamais une nouvelle clé fournisseur.
