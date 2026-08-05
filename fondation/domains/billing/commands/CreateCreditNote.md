---
id: BIL-CMD-CREATE-CREDIT-NOTE
title: CreateCreditNote
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

# CreateCreditNote

## Objectif

Créer une CreditNote `Draft` corrigeant une Invoice émise.

## Agrégat concerné

Nouvel agrégat `CreditNote` ; l'Invoice source est lue sans être mutée.

## Acteur et autorité

Membre autorisé par `billing.credit-notes.create`.

## Données d'entrée

```text
WorkspaceId
CreditNoteId
InvoiceId
CreditNoteDraft
CreateCreditNoteRequestId
ActorContext
```

## Préconditions et traitement

- Invoice source `Issued` dans le même Workspace ;
- devise identique et lignes correctives rattachables au document source ;
- création en `Draft` avec snapshots copiés depuis l'Invoice.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-002`, `BIL-INV-004`–`BIL-INV-008`,
`BIL-INV-027`–`BIL-INV-030`, `BIL-INV-035`, `BIL-INV-037`, `BIL-INV-038`.

## Événements produits

- `CreditNoteCreated`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`,
`ReferenceConflict`, `CalculationConflict`, `Conflict`.

## Idempotence

`CreateCreditNoteRequestId` est obligatoire. La même intention retourne le
Draft initial ; une réutilisation incompatible échoue.
