---
id: NTF-PROC-RECORD-NOTIFICATION-DELIVERY-OUTCOME
title: RecordNotificationDeliveryOutcome
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
  - ../integrations.md
---

# RecordNotificationDeliveryOutcome

## Objectif

Enregistrer une preuve authentifiée que le fournisseur a livré ou définitivement
échoué une soumission précédemment acceptée.

## Agrégat concerné

`Notification` et sa `NotificationDelivery`.

## Acteur et autorité

Adapter fournisseur exécuté par un workload autorisé par
`notifications.deliveries.record-outcome`.

## Données d'entrée

```text
WorkspaceId
NotificationDeliveryId
ProviderOutcomeId
ProviderDeliveryProof
ExpectedRevision
RecordNotificationDeliveryOutcomeRequestId
WorkloadContext
```

## Préconditions

- preuve de signature, fournisseur et schéma valides ;
- ProviderDeliveryReference connue et liée à la même livraison ;
- outcome supporté et ordonné après l'acceptation fournisseur ;
- ExpectedRevision égale à la révision courante.

Un callback déjà dédupliqué retourne son résultat. Un callback tardif ne
réactive jamais une livraison Cancelled, Suppressed ou terminalement Failed.

## Invariants concernés

`NTF-INV-001`–`NTF-INV-003`, `NTF-INV-038`–`NTF-INV-058`.

## Événements produits

- `NotificationDeliveryConfirmed` pour Delivered ;
- `NotificationDeliveryFailed` pour un échec terminal prouvé.

## Erreurs métier

`InvalidInput`, `Unauthenticated`, `Unauthorized`, `NotFound`, `InvalidState`,
`ProviderProofInvalid`, `Conflict`.

## Idempotence

ProviderOutcomeId et RecordNotificationDeliveryOutcomeRequestId sont
obligatoires. Le rejeu identique retourne le fait initial ; une même preuve avec
une autre livraison ou un autre résultat échoue avec `Conflict`.
