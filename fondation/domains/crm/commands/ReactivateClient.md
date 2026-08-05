---
id: CRM-CMD-REACTIVATE-CLIENT
title: ReactivateClient
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

# ReactivateClient

## Objectif

Rendre de nouveau disponible un Client archivé.

## Agrégat concerné

`Client`.

## Acteur et autorité

Membre autorisé par `crm.clients.reactivate`.

## Données d'entrée

```text
WorkspaceId
ClientId
ExpectedRevision
ReactivateClientRequestId
ActorContext
```

## Préconditions et traitement

- Client `Archived` dans le même Workspace ;
- profil courant toujours valide ;
- Workspace actif ;
- révision attendue courante ;
- passage à `Active` sans réactiver automatiquement les Contacts archivés.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-003`, `CRM-INV-004`, `CRM-INV-006`,
`CRM-INV-021`, `CRM-INV-022`, `CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `ClientReactivated`.

## Erreurs métier

`Unauthorized`, `NotFound`, `InvalidState`, `InvalidInput`, `Conflict`.

## Idempotence

`ReactivateClientRequestId` est obligatoire. Même clé retourne la transition
initiale ; une nouvelle demande sur `Active` est invalide.
