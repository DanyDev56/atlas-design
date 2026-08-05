---
id: NTF-CMD-MARK-NOTIFICATION-READ
title: MarkNotificationRead
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../lifecycle.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
---

# MarkNotificationRead

## Objectif

Enregistrer qu'un destinataire a lu sa Notification dans l'inbox Atlas.

## Agrégat concerné

`Notification`.

## Acteur et autorité

User actif disposant de `notifications.inbox.mark-read`, identique au
RecipientUserId de la Notification dans le même Workspace.

## Données d'entrée

```text
WorkspaceId
NotificationId
ExpectedRevision
MarkNotificationReadRequestId
ActorContext
```

## Préconditions

- Notification trouvée dans le même Workspace ;
- acteur identique au RecipientUserId ;
- NotificationReadState égale à Unread ;
- ExpectedRevision égale à la révision courante.

NotificationStatus peut être Active ou terminal : la lecture reste une vérité
orthogonale à la pertinence.

## Invariants concernés

`NTF-INV-001`–`NTF-INV-003`, `NTF-INV-011`–`NTF-INV-013`,
`NTF-INV-031`–`NTF-INV-037`, `NTF-INV-049`–`NTF-INV-058`.

## Événement produit

- `NotificationMarkedRead`.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`,
`Conflict`.

## Idempotence

MarkNotificationReadRequestId est obligatoire. Le rejeu identique retourne la
transition initiale ; sa réutilisation pour une autre Notification échoue avec
`Conflict`.
