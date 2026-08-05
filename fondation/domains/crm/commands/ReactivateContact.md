---
id: CRM-CMD-REACTIVATE-CONTACT
title: ReactivateContact
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
---

# ReactivateContact

## Objectif

Rendre de nouveau utilisable un Contact archivé.

## Agrégat concerné

`Client`.

## Acteur et autorité

Membre autorisé par `crm.contacts.reactivate`.

## Données d'entrée

```text
WorkspaceId
ClientId
ContactId
ExpectedRevision
ReactivateContactRequestId
ActorContext
```

## Préconditions et traitement

- Client actif ;
- Contact `Archived` avec profil valide ;
- révision attendue courante ;
- passage à `Active` sans désignation principale automatique.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-007`, `CRM-INV-008`, `CRM-INV-009`,
`CRM-INV-021`, `CRM-INV-022`, `CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `ContactReactivated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`.

## Idempotence

`ReactivateContactRequestId` est obligatoire. Même clé retourne la réactivation
initiale ; une nouvelle demande sur un Contact actif est invalide.
