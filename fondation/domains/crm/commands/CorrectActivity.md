---
id: CRM-CMD-CORRECT-ACTIVITY
title: CorrectActivity
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

# CorrectActivity

## Objectif

Corriger le contenu d'une Activity tout en conservant la valeur précédente.

## Agrégat concerné

`Activity`.

## Acteur et autorité

Membre autorisé par `crm.activities.correct`.

## Données d'entrée

```text
WorkspaceId
ActivityId
CorrectedContent
CorrectionReason
ExpectedRevision
CorrectActivityRequestId
ActorContext
```

## Préconditions et traitement

- Activity `Recorded` dans le Workspace ;
- correction et raison non vides ;
- références Client, Contact et Opportunity inchangées ;
- révision attendue courante ;
- ajout d'une révision et conservation auditée de l'ancienne valeur.

## Invariants concernés

`CRM-INV-001`, `CRM-INV-016`, `CRM-INV-017`, `CRM-INV-018`,
`CRM-INV-019`, `CRM-INV-021`, `CRM-INV-022`, `CRM-INV-023`,
`CRM-INV-024`.

## Événements produits

- `ActivityCorrected`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`ReferenceConflict`, `Conflict`.

## Idempotence

`CorrectActivityRequestId` est obligatoire. Un retry identique retourne la
révision créée sans dupliquer la correction.
