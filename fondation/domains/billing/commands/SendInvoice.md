---
id: BIL-CMD-SEND-INVOICE
title: SendInvoice
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

# SendInvoice

## Objectif

Demander la livraison d'une Invoice émise et de son artefact immuable.

## Agrégat concerné

`Invoice`.

## Acteur et autorité

Membre autorisé par `billing.invoices.send`.

## Données d'entrée

```text
WorkspaceId
InvoiceId
DeliveryInstruction
ExpectedRevision
SendInvoiceRequestId
ActorContext
```

## Préconditions et traitement

- Invoice `Issued` et artefact vérifié disponible ;
- destination valide issue de l'instruction, sans modifier le snapshot ;
- preuve publique de lecture et message d'outbox créés atomiquement.

## Invariants concernés

`BIL-INV-001`, `BIL-INV-018`, `BIL-INV-032`–`BIL-INV-038`.

## Événements produits

- `InvoiceDeliveryRequested`.

## Erreurs métier

`InvalidInput`, `Unauthorized`, `NotFound`, `InvalidState`, `Conflict`,
`TemporarilyUnavailable`.

## Idempotence

`SendInvoiceRequestId` est obligatoire. Les retries réutilisent la même demande
et la même preuve publique.
