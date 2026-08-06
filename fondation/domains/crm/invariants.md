---
id: CRM-INVARIANTS
title: CRM Invariants
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - model.md
  - entities.md
  - aggregates.md
  - value-objects.md
  - events.md
---

# Invariants

## Catalogue

| ID | Règle absolue |
|---|---|
| `CRM-INV-001` | Toute entité CRM appartient durablement à un seul Workspace. |
| `CRM-INV-002` | Les identifiants CRM sont stables, typés et non réutilisables. |
| `CRM-INV-003` | Le cycle Client autorise uniquement `Active ↔ Archived`. |
| `CRM-INV-004` | Un Client actif possède des profils commercial et administratif structurellement valides. |
| `CRM-INV-005` | Un Client avec une Opportunity non terminale ne peut pas être archivé. |
| `CRM-INV-006` | L'archivage CRM est logique et ne détruit aucun historique. |
| `CRM-INV-007` | Un Contact appartient à exactement un Client et au même Workspace. |
| `CRM-INV-008` | Le Contact principal est actif et contenu dans le même Client. |
| `CRM-INV-009` | Le cycle Contact autorise uniquement `Active ↔ Archived`. |
| `CRM-INV-010` | Un Contact référencé par une Opportunity non terminale ne peut pas être archivé. |
| `CRM-INV-011` | Une Opportunity conserve le même Client pendant toute sa vie. |
| `CRM-INV-012` | Une référence Contact d'Opportunity désigne un Contact actif du même Client. |
| `CRM-INV-013` | Le cycle Opportunity suit uniquement les transitions autorisées. |
| `CRM-INV-014` | `Won` et `Lost` sont terminaux et mutuellement exclusifs. |
| `CRM-INV-015` | Une estimation monétaire est valide, non négative et non rétroactive. |
| `CRM-INV-016` | Une Activity décrit un fait non futur et rattaché à un Client existant. |
| `CRM-INV-017` | Les références optionnelles d'une Activity appartiennent au même Client et Workspace. |
| `CRM-INV-018` | Une correction d'Activity préserve toutes les valeurs antérieures. |
| `CRM-INV-019` | Une Activity retirée est terminale et reste auditée. |
| `CRM-INV-020` | Un snapshot externe n'est jamais réécrit par une mutation CRM. |
| `CRM-INV-021` | Toute intention humaine est autorisée dans le même Workspace actif. |
| `CRM-INV-022` | Toute mutation vérifie la révision attendue. |
| `CRM-INV-023` | Toute commande est idempotente dans sa portée métier. |
| `CRM-INV-024` | État, Domain Events et outbox sont enregistrés atomiquement. |
| `CRM-INV-025` | Pipeline est une projection et ne possède aucun état métier indépendant. |
| `CRM-INV-026` | Un Client importé conserve une provenance, un instant source et un hash immuables sans perdre son identité Atlas. |
| `CRM-INV-027` | Une identité externe historique est unique dans `(WorkspaceId, SourceSystem, RecordKind, ExternalId)` et tout rejeu divergent est refusé. |
| `CRM-INV-028` | Un import historique ne produit aucun événement opérationnel et ne devient visible qu'après validation de son manifest complet. |

---

## CRM-INV-001 — Isolation par Workspace

`WorkspaceId` est fixé à la création. Toute référence Client, Contact,
Opportunity ou Activity est résolue avec ce contexte.

Une valeur provenant d'un autre Workspace produit un refus, même si
l'identifiant existe.

---

## CRM-INV-002 — Identités stables

Un nom, une adresse e-mail, un identifiant fiscal ou un titre d'Opportunity ne
constitue jamais une identité. Un identifiant archivé ou terminal n'est pas
recyclé.

---

## CRM-INV-003 — Cycle Client

```text
Active -> Archived
Archived -> Active
```

Une demande répétée avec une nouvelle clé est un état invalide, pas un nouvel
événement.

---

## CRM-INV-004 — Profils Client

Un Client actif possède :

- un `DisplayName` non vide ;
- un `ClientKind` supporté ;
- des coordonnées et adresses valides lorsqu'elles existent ;
- aucune credential ou donnée secrète.

Son `ClientBillingProfile`, même incomplet, respecte les types, formats et règles
d'unicité des identifiants déclarés. Billing reste responsable de déterminer sa
complétude pour un document donné.

Le nom n'est pas soumis à une unicité dure.

---

## CRM-INV-005 — Archivage sans vente active

Avant `ArchiveClient`, aucun index cohérent ne doit signaler d'Opportunity
`Open` ou `Qualified` pour ce Client.

L'utilisateur gagne ou perd d'abord chaque Opportunity, ou annule l'archivage.

---

## CRM-INV-006 — Conservation

Archiver un Client ou Contact conserve :

- l'identifiant et le Workspace ;
- les références depuis les faits historiques ;
- les versions de profil ;
- les événements et audits ;
- les snapshots détenus par les consommateurs.

L'effacement de données personnelles est un traitement de conformité distinct.

---

## CRM-INV-007 — Appartenance du Contact

Le `ClientId` d'un Contact est déterminé par l'agrégat qui le contient et ne peut
pas être modifié. Un changement de contrepartie crée un nouveau Contact et
archive l'ancien si nécessaire.

---

## CRM-INV-008 — Contact principal

`PrimaryContactId` est nul ou désigne un Contact `Active` contenu dans le Client.
Un seul Contact principal existe à la fois.

Archiver le Contact principal efface cette désignation dans le même commit.

---

## CRM-INV-009 — Cycle Contact

```text
Active -> Archived
Archived -> Active
```

Réactiver un Contact exige un Client actif.

---

## CRM-INV-010 — Contact utilisé par une vente active

Un Contact ne peut pas être archivé tant qu'une Opportunity `Open` ou
`Qualified` le référence. L'Opportunity doit d'abord être réaffectée ou devenir
terminale.

---

## CRM-INV-011 — Client immuable d'une Opportunity

Une vente avec une autre contrepartie est une autre Opportunity. Cette règle
préserve les événements, analyses et liens vers Billing.

---

## CRM-INV-012 — Contact courant de l'Opportunity

Lors de la création ou du remplacement, le Contact :

- existe ;
- est actif ;
- appartient au Client de l'Opportunity ;
- appartient au même Workspace.

Une référence historique reste interprétable après la terminaison.

---

## CRM-INV-013 — Cycle Opportunity

```text
Open      -> Qualified
Open      -> Lost
Qualified -> Won
Qualified -> Lost
```

Une Opportunity ouverte ne peut pas être gagnée sans qualification explicite.
Le workflow rapide peut enchaîner `QualifyOpportunity` puis `WinOpportunity`
avec la même corrélation, sans fusionner les intentions.

---

## CRM-INV-014 — Résultat terminal

Une Opportunity ne peut être simultanément gagnée et perdue. Un résultat
terminal conserve sa source, son instant et l'acteur auditable.

Une vente ultérieure crée une nouvelle Opportunity.

---

## CRM-INV-015 — Estimation monétaire

`EstimatedAmount` :

- possède une devise supportée ;
- n'est jamais négatif ;
- reste une estimation, pas une créance ;
- conserve sa valeur historique dans les événements et projections ;
- n'est pas converti après un changement de préférence Workspace.

---

## CRM-INV-016 — Activity passée

`OccurredAt` ne dépasse pas l'horloge de référence au-delà de la tolérance
documentée. Une réunion future est une planification, pas une Activity.

Le Client doit exister et être actif au moment de l'enregistrement manuel.

---

## CRM-INV-017 — Références Activity

Si présents :

- `ContactId` appartient au Client ;
- `OpportunityId` appartient au Client ;
- toutes les entités appartiennent au Workspace de la commande.

Une Activity historique reste valide après archivage ou terminaison de ses
références.

---

## CRM-INV-018 — Correction traçable

Une correction ajoute une révision et un événement. Elle ne modifie ni
`OccurredAt` au point de transformer la nature du fait, ni l'identité de
l'Activity sans conserver les valeurs précédentes.

Une erreur portant sur un autre Client exige le retrait puis un nouvel
enregistrement.

---

## CRM-INV-019 — Retrait terminal d'Activity

`Removed` n'est plus visible dans les lectures ordinaires mais reste disponible
dans l'audit autorisé. Aucune correction ni restauration 1.0 n'est permise.

---

## CRM-INV-020 — Non-rétroactivité externe

Les mises à jour Client ou Opportunity produisent une nouvelle version. Elles ne
modifient jamais :

- une Quote ou Invoice ;
- un snapshot Analytics ;
- une Recommendation déjà expliquée ;
- un événement antérieur.

---

## CRM-INV-021 — Autorisation

Une commande humaine exige : principal actif, Workspace actif, Membership actif,
permission exacte et références dans ce même Workspace.

Une orchestration système possède une capacité distincte et une causalité
vérifiable.

---

## CRM-INV-022 — Concurrence

Chaque mutation reçoit `ExpectedRevision`. Une valeur obsolète produit
`Conflict` sans événement de réussite.

---

## CRM-INV-023 — Idempotence

La portée inclut l'intention, l'acteur et le Workspace. Même clé et même
empreinte retournent le résultat initial ; une autre empreinte est refusée.

---

## CRM-INV-024 — Atomicité

Le nouvel état, les Domain Events et l'entrée d'outbox deviennent visibles
ensemble. Un refus ne produit aucun fait de réussite.

---

## CRM-INV-025 — Pipeline dérivé

Une colonne de pipeline correspond à un statut canonique. Aucun déplacement,
ordre manuel ou libellé d'interface ne peut contourner une commande Opportunity.

---

## CRM-INV-026 — Provenance historique

Un Client importé reçoit un `ClientId` Atlas et un
`HistoricalImportProvenance` immuable. Sa date source ne remplace jamais
`ImportedAt`, et son hash permet de prouver ce qui a été accepté sans conserver
le package brut.

---

## CRM-INV-027 — Identité externe

La clé externe est bornée au Workspace, au système déclaré et au type de ligne.
Un rejeu identique converge vers le Client initial. Une ligne différente sous
la même clé produit `Conflict` ; aucun merge heuristique n'est exécuté.

---

## CRM-INV-028 — Barrière de completion

Les Clients d'un run restent hors des lectures courantes et des contrats
Analytics tant que compteurs, checkpoints et hash du manifest ne concordent
pas. La completion les rend visibles ensemble du point de vue du contrat ; elle
publie `ClientHistoryImportCompleted`, jamais `ClientCreated`.
