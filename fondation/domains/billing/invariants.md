---
id: BIL-INVARIANTS
title: Billing Invariants
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - model.md
  - aggregates.md
  - value-objects.md
  - events.md
  - commands/README.md
---

# Invariants

Les invariants suivants sont absolus pour Billing 1.0. Une commande qui ne peut
pas tous les préserver est refusée sans événement de réussite.

## Frontière et identité

| ID | Règle |
|---|---|
| `BIL-INV-001` | Tout agrégat, référence, permission et snapshot appartient à un seul `WorkspaceId`. |
| `BIL-INV-002` | Les identifiants sont stables, uniques et jamais réattribués. |
| `BIL-INV-003` | Aucune suppression physique ne retire un document, paiement ou fait financier de l'historique métier. |

## Valeurs financières et snapshots

| ID | Règle |
|---|---|
| `BIL-INV-004` | Les calculs utilisent une représentation décimale exacte ; aucun flottant binaire n'est canonique. |
| `BIL-INV-005` | Un document et toutes ses lignes utilisent une seule devise ; aucune conversion rétroactive n'est permise. |
| `BIL-INV-006` | Sous-total, taxes, remises et total sont calculés et arrondis par une politique déterministe versionnée. |
| `BIL-INV-007` | Les snapshots Client, émetteur et Opportunity requis sont complets avant finalisation ou émission. |
| `BIL-INV-008` | Un snapshot figé n'est jamais réécrit par une évolution de CRM ou Workspace. |
| `BIL-INV-009` | Un numéro de document alloué par Atlas est unique dans son espace, type, série et période ; il est monotone, audité et jamais réutilisé. |

## Quote

| ID | Règle |
|---|---|
| `BIL-INV-010` | Les seules transitions opérationnelles sont `Draft -> Sending -> Sent -> Accepted | Rejected | Withdrawn | Expired`, plus `Sending -> Withdrawn`. |
| `BIL-INV-011` | Le contenu commercial et financier d'une Quote n'est modifiable qu'en `Draft`. |
| `BIL-INV-012` | `SendQuote` valide les données, alloue le numéro, fige les snapshots et crée une preuve d'accès avant de demander la livraison. |
| `BIL-INV-013` | Une réponse publique exige une preuve valide, bornée au document et au Workspace ; acceptation et rejet sont mutuellement exclusifs et terminaux. |
| `BIL-INV-014` | Une Quote n'expire qu'en `Sent`, après `ValidUntil`, sous l'autorité de l'horloge ; son retrait reste une intention explicite. |
| `BIL-INV-015` | Seule une Quote `Accepted` peut produire une Invoice de dépôt ou finale. |
| `BIL-INV-016` | Une Quote possède au plus une `DepositInvoice` et une `FinalInvoice` ; leur allocation cumulée n'excède jamais son total accepté. |

## Invoice et règlement

| ID | Règle |
|---|---|
| `BIL-INV-017` | Les seules transitions documentaires opérationnelles d'une Invoice sont `Draft -> Issued` ou `Draft -> Discarded`. |
| `BIL-INV-018` | Une Invoice `Draft` est modifiable ; après émission, son contenu financier, sa devise, son numéro et ses snapshots sont immuables. |
| `BIL-INV-019` | Une Invoice émise possède un numéro, une date d'émission, une échéance, des snapshots complets et des totaux valides. |
| `BIL-INV-020` | `OutstandingBalance = IssuedTotal - paiements actifs appliqués - avoirs appliqués`. |
| `BIL-INV-021` | Le solde d'une Invoice ne devient jamais négatif. |
| `BIL-INV-022` | `SettlementStatus` est dérivé : total si solde initial, partiel entre zéro et total, réglé si zéro. |
| `BIL-INV-023` | Un Payment appartient à une seule Invoice émise du même Workspace et utilise sa devise. |
| `BIL-INV-024` | `AmountReceived = AmountApplied + UnappliedAmount`, avec `AmountApplied` positif et au plus égal au solde avant application. |
| `BIL-INV-025` | Toute somme reçue mais non appliquée porte une disposition explicite `RefundDue` ou `ClientCredit`. |
| `BIL-INV-026` | Un Payment enregistré n'est ni édité ni supprimé ; seule une inversion complète, motivée et auditée est autorisée. |

## CreditNote, échéance et artefact

| ID | Règle |
|---|---|
| `BIL-INV-027` | Une CreditNote corrige exactement une Invoice émise du même Workspace et dans la même devise. |
| `BIL-INV-028` | Les seules transitions d'une CreditNote sont `Draft -> Issued -> Applied` ou `Draft -> Discarded`; son contenu est immuable après émission. |
| `BIL-INV-029` | Le total des CreditNotes émises ne dépasse pas le total brut de l'Invoice source. |
| `BIL-INV-030` | Le montant appliqué d'une CreditNote ne dépasse ni son total ni le solde courant ; tout reliquat porte `RefundDue` ou `ClientCredit`. |
| `BIL-INV-031` | Une Invoice est en retard seulement si elle est émise, échue et non réglée ; le fait est émis au plus une fois par passage en retard. |
| `BIL-INV-032` | Un artefact PDF publié correspond exactement à une version immuable, identifiée par son hash ; il n'est jamais la source des calculs. |

## Accès, livraison et fiabilité

| ID | Règle |
|---|---|
| `BIL-INV-033` | Les preuves publiques sont opaques, stockées sous forme de hash, révocables, expirables et bornées à une capacité précise. |
| `BIL-INV-034` | Une demande de livraison, son acceptation par le fournisseur et une ouverture sont trois faits distincts ; aucun ne prouve la réception humaine. |
| `BIL-INV-035` | Toute intention humaine exige un acteur actif et une permission effective dans le même Workspace. |
| `BIL-INV-036` | Toute mutation d'un agrégat existant compare sa révision attendue ; une opération multi-agrégats compare toutes les révisions concernées. |
| `BIL-INV-037` | Chaque commande possède un RequestId ; une répétition identique retourne le résultat initial et une réutilisation incompatible échoue. |
| `BIL-INV-038` | État, numéros réservés, événements et messages d'outbox sont commis atomiquement ; les consommateurs dédupliquent `EventId`. |

## Import historique

| ID | Règle |
|---|---|
| `BIL-INV-039` | Toute ligne importée conserve une provenance, un instant source et un hash immuables sous une identité Atlas stable. |
| `BIL-INV-040` | Une identité externe est unique dans `(WorkspaceId, SourceSystem, RecordKind, ExternalId)` ; un rejeu divergent est refusé. |
| `BIL-INV-041` | Le numéro, les dates et l'état source d'un document importé sont conservés ; son numéro n'est jamais alloué par ni injecté dans une séquence Atlas. |
| `BIL-INV-042` | Les soldes importés sont recalculés depuis Invoices, Payments et avoirs supportés ; tout écart non résolu bloque la completion. |
| `BIL-INV-043` | Un import n'émet aucun fait opérationnel et ne déclenche ni numérotation, rendu, preuve publique, livraison, ouverture ou communication. |
| `BIL-INV-044` | Les agrégats d'un run ne deviennent visibles aux consommateurs Analytics qu'après validation du manifest, des compteurs et des soldes. |

Le chargement d'un état historique supporté n'est pas une transition du cycle
opérationnel défini par `BIL-INV-010` ou `BIL-INV-017`.
