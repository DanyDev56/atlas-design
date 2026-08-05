---
id: NTF-PROC-EXPIRE-NOTIFICATION
title: ExpireNotification
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
  - ../workflows.md
---

# ExpireNotification

## Objectif

Matérialiser la fin de validité d'une Notification active même en l'absence d'un
nouvel événement Advisor.

## Agrégat concerné

`Notification`.

## Acteur et autorité

Scheduler ou workload autorisé par `notifications.items.expire`.

## Données d'entrée

```text
WorkspaceId
NotificationId
ClockProof
ExpectedRevision
ExpireNotificationRequestId
WorkloadContext
```

## Préconditions

- Notification Active ;
- DisplayUntil atteint selon ClockProof authentique ;
- ExpectedRevision égale à la révision courante.

La transition annule atomiquement toute livraison Pending. Une livraison déjà
Dispatching converge séparément vers son outcome fournisseur et ne peut plus
être présentée comme annulée.

## Invariants concernés

`NTF-INV-001`–`NTF-INV-003`, `NTF-INV-031`–`NTF-INV-039`,
`NTF-INV-044`–`NTF-INV-058`.

## Événements produits

- `NotificationExpired` ;
- `NotificationDeliveryCancelled` pour chaque livraison encore annulable.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`,
`Conflict`.

## Idempotence

ExpireNotificationRequestId est obligatoire. Le rejeu identique retourne la
transition initiale ; sa réutilisation pour une autre Notification ou une autre
preuve échoue avec `Conflict`.
