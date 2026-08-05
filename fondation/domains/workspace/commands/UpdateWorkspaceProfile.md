---
id: WSP-CMD-UPDATE-WORKSPACE-PROFILE
title: UpdateWorkspaceProfile
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../value-objects.md
  - ../invariants.md
  - ../events.md
  - ../permissions.md
---

# UpdateWorkspaceProfile

## Objectif

Remplacer tout ou partie du profil commercial courant d'un Workspace.

## Agrégat concerné

`Workspace`.

## Acteur et autorité

Un membre autorisé par `workspace.profile.update` dans le même Workspace.

## Données d'entrée

```text
WorkspaceId
ProfileChanges
ExpectedRevision
UpdateWorkspaceProfileRequestId
ActorContext
```

## Préconditions

- Workspace `Active` ;
- décision Identity valide et contextualisée ;
- changements non vides ;
- profil résultant valide ;
- révision attendue courante.

## Traitement métier

1. appliquer les changements à une copie immuable ;
2. normaliser et valider le profil complet résultant ;
3. refuser une absence de changement métier ;
4. remplacer le profil et incrémenter `ProfileVersion` ;
5. publier uniquement les catégories de champs modifiées.

## Résultat attendu

La nouvelle version est disponible pour les lectures futures. Aucun snapshot
historique externe n'est modifié.

## Invariants concernés

- `WSP-INV-003` ;
- `WSP-INV-006` ;
- `WSP-INV-008` ;
- `WSP-INV-011` ;
- `WSP-INV-013` ;
- `WSP-INV-014` ;
- `WSP-INV-015` ;
- `WSP-INV-017`.

## Événements produits

- `WorkspaceProfileUpdated`.

## Erreurs métier

- `InvalidInput` ;
- `Unauthenticated` ;
- `Unauthorized` ;
- `NotFound` ;
- `InvalidState` ;
- `Conflict`.

## Idempotence

`UpdateWorkspaceProfileRequestId` est obligatoire. Même clé et même patch
retournent la version déjà produite ; une autre empreinte est en conflit.

## Décisions de conception

Le nom d'affichage n'est pas globalement unique. Les conflits d'URL ou de slug
appartiennent à l'adaptateur de navigation, pas à l'identité métier.
