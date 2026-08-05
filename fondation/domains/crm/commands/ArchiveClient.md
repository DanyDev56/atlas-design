---
id: CRM-CMD-ARCHIVE-CLIENT
title: ArchiveClient
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
  - ../workflows.md
---

# ArchiveClient

## Objectif

Retirer un Client de l'usage courant sans supprimer son histoire.

## Agrégat concerné

`Client`, avec contrôle cohérent des Opportunity référentes.

## Acteur et autorité

Membre autorisé par `crm.clients.archive`.

## Données d'entrée

```text
WorkspaceId
ClientId
ArchiveReason
ExpectedRevision
ArchiveClientRequestId
ActorContext
```

## Préconditions et traitement

- Client `Active` dans le Workspace ;
- aucune Opportunity `Open` ou `Qualified` ;
- révision attendue courante ;
- passage à `Archived`, conservation des Contacts et historiques.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-003`, `CRM-INV-005`, `CRM-INV-006`,
`CRM-INV-021`, `CRM-INV-022`, `CRM-INV-023`, `CRM-INV-024`.

## Événements produits

- `ClientArchived`.

## Erreurs métier

`Unauthorized`, `NotFound`, `InvalidState`, `ActiveOpportunityExists`,
`Conflict`, `TemporarilyUnavailable`.

## Idempotence

`ArchiveClientRequestId` est obligatoire. Un retry identique retourne le succès
initial. Une nouvelle clé sur un Client archivé produit `InvalidState`.
