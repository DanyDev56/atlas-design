---
id: NTF-CMD-MARK-NOTIFICATION-UNREAD
title: MarkNotificationUnread
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

# MarkNotificationUnread

## Objectif

Replacer une Notification lue dans la file personnelle à relire.

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
MarkNotificationUnreadRequestId
ActorContext
```

## Préconditions

- Notification trouvée dans le même Workspace ;
- acteur identique au RecipientUserId ;
- NotificationReadState égale à Read ;
- ExpectedRevision égale à la révision courante.

La commande ne réactive jamais une Notification terminale et ne redemande
aucune livraison.

## Invariants concernés

`NTF-INV-001`–`NTF-INV-003`, `NTF-INV-011`–`NTF-INV-013`,
`NTF-INV-031`–`NTF-INV-037`, `NTF-INV-049`–`NTF-INV-058`.

## Événement produit

- `NotificationMarkedUnread`.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`,
`Conflict`.

## Idempotence

MarkNotificationUnreadRequestId est obligatoire. Le rejeu identique retourne
la transition initiale ; sa réutilisation pour une autre Notification échoue
avec `Conflict`.
