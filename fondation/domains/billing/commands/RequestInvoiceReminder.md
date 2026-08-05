---
id: BIL-CMD-REQUEST-INVOICE-REMINDER
title: RequestInvoiceReminder
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

# RequestInvoiceReminder

## Objectif

Demander manuellement une relance pour une Invoice non réglée.

## Agrégat concerné

`Invoice`.

## Acteur et autorité

Membre autorisé par `billing.invoices.remind`.

## Données d'entrée

```text
WorkspaceId
InvoiceId
DeliveryInstruction
ReminderMessage?
ExpectedRevision
RequestInvoiceReminderRequestId
ActorContext
```

## Préconditions et traitement

- Invoice `Issued` avec solde positif ;
- message optionnel borné et assaini ;
- artefact émis inchangé ;
- demande de relance placée atomiquement dans l'outbox.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-018`, `BIL-INV-020`–`BIL-INV-022`,
`BIL-INV-032`, `BIL-INV-034`–`BIL-INV-038`.

## Événements produits

- `InvoiceReminderRequested`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`,
`TemporarilyUnavailable`.

## Idempotence

`RequestInvoiceReminderRequestId` est obligatoire. Les retries réutilisent la
même relance ; une nouvelle relance exige un nouveau RequestId.
