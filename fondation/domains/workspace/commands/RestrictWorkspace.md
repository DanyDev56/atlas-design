---
id: WSP-CMD-RESTRICT-WORKSPACE
title: RestrictWorkspace
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

# RestrictWorkspace

## Objectif

Suspendre l'usage ordinaire d'un Workspace sans fermer ni supprimer ses données.

## Agrégat concerné

`Workspace`.

## Acteur et autorité

Workflow de sécurité ou conformité possédant la capacité SystemActorOnly
`workspace.lifecycle.restrict`.

## Données d'entrée

```text
WorkspaceId
RestrictionContext
ExpectedRevision
RestrictWorkspaceRequestId
CorrelationId
```

## Préconditions

- Workspace `Active` ;
- raison appartenant au catalogue supporté ;
- acteur système borné au Workspace ;
- révision attendue courante.

## Traitement métier

1. valider l'autorité et la raison ;
2. passer à `Restricted` ;
3. conserver le contexte auditable ;
4. incrémenter `GovernanceVersion` ;
5. publier les deux faits de cycle de vie.

## Résultat attendu

L'accès ordinaire est refusé immédiatement. Les données et historiques sont
conservés.

## Invariants concernés

- `WSP-INV-002` ;
- `WSP-INV-005` ;
- `WSP-INV-010` ;
- `WSP-INV-012` ;
- `WSP-INV-013` ;
- `WSP-INV-014` ;
- `WSP-INV-015` ;
- `WSP-INV-016` ;
- `WSP-INV-017`.

## Événements produits

- `WorkspaceRestricted` ;
- `WorkspaceAccessStateChanged`.

## Erreurs métier

- `InvalidInput` ;
- `Unauthorized` ;
- `NotFound` ;
- `InvalidState` ;
- `Conflict`.

## Idempotence

`RestrictWorkspaceRequestId` est obligatoire. Un retry identique retourne la
même transition. Une restriction distincte d'un Workspace déjà restreint exige
un futur contrat explicite ; elle n'écrase pas silencieusement la raison.

## Décisions de conception

Une pause volontaire de l'utilisateur n'est pas assimilée à une restriction de
sécurité en 1.0.
