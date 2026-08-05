---
id: WSP-CMD-ACTIVATE-WORKSPACE
title: ActivateWorkspace
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - ../invariants.md
  - ../events.md
  - ../workflows.md
  - ../integrations.md
---

# ActivateWorkspace

## Objectif

Rendre utilisable un Workspace dont le bootstrap local et Identity est complet.

## Agrégat concerné

`Workspace`.

## Acteur et autorité

Workflow de bootstrap possédant la capacité SystemActorOnly
`workspace.lifecycle.activate`.

## Données d'entrée

```text
WorkspaceId
OwnerReadinessProof
ExpectedRevision
ActivateWorkspaceRequestId
CorrelationId
```

## Préconditions

- statut courant `Provisioning` ;
- profil et préférences valides ;
- preuve récente, authentique et portant le même Workspace ;
- `HasActiveOwner = true` ;
- révision attendue courante.

## Traitement métier

1. valider la preuve sans charger le modèle Identity ;
2. passer de `Provisioning` à `Active` ;
3. incrémenter `GovernanceVersion` ;
4. enregistrer l'instant de première activation ;
5. publier les faits atomiquement.

## Résultat attendu

L'état d'accès public passe de `Restricted` à `Active`.

## Invariants concernés

- `WSP-INV-002` ;
- `WSP-INV-003` ;
- `WSP-INV-004` ;
- `WSP-INV-005` ;
- `WSP-INV-010` ;
- `WSP-INV-013` ;
- `WSP-INV-014` ;
- `WSP-INV-015` ;
- `WSP-INV-018`.

## Événements produits

- `WorkspaceActivated` ;
- `WorkspaceAccessStateChanged`.

## Erreurs métier

- `NotFound` ;
- `Unauthorized` ;
- `InvalidState` ;
- `ReadinessRequired` ;
- `Conflict` ;
- `TemporarilyUnavailable`.

## Idempotence

`ActivateWorkspaceRequestId` est obligatoire. Un retry identique après succès
retourne le résultat initial. Une nouvelle clé sur un Workspace déjà actif
produit `InvalidState`.

## Concurrence

La commande vérifie `ExpectedRevision`. Une preuve expirée est rejetée même
après un retry techniquement valide.
