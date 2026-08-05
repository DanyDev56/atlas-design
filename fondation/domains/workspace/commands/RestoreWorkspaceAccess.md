---
id: WSP-CMD-RESTORE-WORKSPACE-ACCESS
title: RestoreWorkspaceAccess
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

# RestoreWorkspaceAccess

## Objectif

Lever une restriction après remédiation et rétablir l'usage ordinaire.

## Agrégat concerné

`Workspace`.

## Acteur et autorité

Workflow de sécurité ou conformité possédant la capacité SystemActorOnly
`workspace.lifecycle.restore`.

## Données d'entrée

```text
WorkspaceId
OwnerReadinessProof
RemediationReference
ExpectedRevision
RestoreWorkspaceRequestId
CorrelationId
```

## Préconditions

- Workspace `Restricted` ;
- remédiation confirmée par l'autorité d'origine ou sa politique ;
- preuve Identity récente avec owner actif ;
- révision attendue courante.

## Traitement métier

1. valider remédiation, autorité et preuve ;
2. passer à `Active` ;
3. clôturer le contexte de restriction sans effacer l'audit ;
4. incrémenter `GovernanceVersion` ;
5. publier les faits.

## Résultat attendu

L'état public passe de `Restricted` à `Active`.

## Invariants concernés

- `WSP-INV-002` ;
- `WSP-INV-003` ;
- `WSP-INV-004` ;
- `WSP-INV-005` ;
- `WSP-INV-010` ;
- `WSP-INV-012` ;
- `WSP-INV-013` ;
- `WSP-INV-014` ;
- `WSP-INV-015` ;
- `WSP-INV-017`.

## Événements produits

- `WorkspaceAccessRestored` ;
- `WorkspaceAccessStateChanged`.

## Erreurs métier

- `Unauthorized` ;
- `NotFound` ;
- `InvalidState` ;
- `ReadinessRequired` ;
- `Conflict` ;
- `TemporarilyUnavailable`.

## Idempotence

`RestoreWorkspaceRequestId` est obligatoire. Un retry identique retourne la
même version de gouvernance. Une preuve expirée lors du premier traitement est
refusée et doit être renouvelée.

## Concurrence

Une fermeture concurrente gagne sur la restauration : `Closed` reste terminal
et la commande échoue sur révision ou statut.
