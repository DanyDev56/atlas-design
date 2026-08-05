---
id: WSP-CMD-CLOSE-WORKSPACE
title: CloseWorkspace
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
  - ../integrations.md
---

# CloseWorkspace

## Objectif

Fermer logiquement et définitivement un Workspace après les contrôles de
readiness appropriés.

## Agrégat concerné

`Workspace`.

## Acteur et autorité

- un owner autorisé par `workspace.lifecycle.close`, avec élévation récente et
  confirmation explicite ;
- ou un workflow de conformité possédant
  `workspace.lifecycle.close-for-compliance`.

## Données d'entrée

```text
WorkspaceId
ClosureReason
ClosureReadinessProof
ExplicitConfirmation
ExpectedRevision
CloseWorkspaceRequestId
ActorContext
CorrelationId
```

## Préconditions

- pour un owner, statut `Active` ;
- pour un workflow de conformité, statut `Provisioning`, `Active` ou
  `Restricted` ;
- autorité adaptée à l'origine et à l'état de la fermeture ;
- confirmation non ambiguë pour une demande humaine ;
- readiness des domaines obligatoires ou dérogation de conformité documentée ;
- révision attendue courante.

## Traitement métier

1. vérifier autorité, step-up, confirmation et readiness ;
2. passer à `Closed` ;
3. enregistrer le contexte minimal de fermeture ;
4. incrémenter `GovernanceVersion` ;
5. publier les faits atomiquement ;
6. laisser chaque domaine appliquer sa rétention et ses suites légales.

## Résultat attendu

L'usage ordinaire est définitivement désactivé. L'identifiant et l'historique
restent conservés.

## Invariants concernés

- `WSP-INV-001` ;
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

- `WorkspaceClosed` ;
- `WorkspaceAccessStateChanged`.

## Erreurs métier

- `InvalidInput` ;
- `Unauthenticated` ;
- `Unauthorized` ;
- `NotFound` ;
- `InvalidState` ;
- `ClosureBlocked` ;
- `Conflict` ;
- `TemporarilyUnavailable`.

## Idempotence

`CloseWorkspaceRequestId` est obligatoire. Le même request et la même preuve
retournent la fermeture existante. Une nouvelle demande sur `Closed` produit
`InvalidState` sans nouveau Domain Event.

## Décisions de conception

La commande n'annule pas automatiquement les factures, invitations ou
memberships. Les consommateurs réagissent au fait public selon leur propre
modèle et conservent l'historique requis.
