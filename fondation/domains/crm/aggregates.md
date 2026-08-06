---
id: CRM-AGGREGATES
title: CRM Aggregates
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - model.md
  - entities.md
  - relationships.md
  - invariants.md
  - workflows.md
---

# Agrégats

## Vue d'ensemble

| Agrégat | Racine | Références externes |
|---|---|---|
| Client | `Client` | `WorkspaceId` |
| Opportunity | `Opportunity` | `WorkspaceId`, `ClientId`, `ContactId?` |
| Activity | `Activity` | `WorkspaceId`, `ClientId`, `ContactId?`, `OpportunityId?` |
| Client History Import | `ClientHistoryImportRun` | `WorkspaceId`, package canonique opaque |

---

## Agrégat Client

Le Client contient ses Contacts afin de garantir localement :

- leur appartenance unique ;
- la validité du Contact principal ;
- l'archivage atomique du Contact principal ;
- la stabilité de leurs identifiants.

Le nombre de Contacts reste borné par une politique d'usage. Une extraction en
agrégats séparés nécessitera une décision future si la cible produit évolue.

---

## Agrégat Opportunity

L'Opportunity protège son propre cycle de vente. Elle valide Client et Contact
par un contexte public CRM au moment d'une mutation, sans charger l'agrégat
Client comme objet imbriqué.

Le `ClientId` ne change jamais. Une vente concernant une autre contrepartie est
une nouvelle Opportunity.

---

## Agrégat Activity

Chaque Activity est indépendante pour éviter qu'un historique croissant ne
verrouille le Client. Une correction ajoute une révision au même agrégat et
préserve la valeur antérieure dans l'audit.

---

## Agrégat Client History Import

Le run protège identité du package, checkpoints, compteurs et manifest final.
Les Clients sont matérialisés par transactions bornées et restent invisibles aux
lectures courantes jusqu'à la validation du manifest. Un retry reprend le même
run ; il ne recrée ni ne fusionne silencieusement les Clients déjà validés.

---

## Invariants transverses

Certaines commandes vérifient des projections ou index cohérents :

- un Client ne peut être archivé avec une Opportunity ouverte ou qualifiée ;
- un Contact référencé par une Opportunity non terminale ne peut être archivé ;
- les références Activity appartiennent au même Client et Workspace.

Ces contrôles utilisent des verrous ou une garantie transactionnelle adaptée.
Une projection éventuellement cohérente ne suffit pas à préserver un invariant
absolu.

---

## Frontières transactionnelles

Une commande modifie un seul agrégat par défaut. L'archivage d'un Contact reste
dans l'agrégat Client. Les contrôles sur Opportunity utilisent un index de
référence sans modifier l'Opportunity.

État, événements et outbox sont enregistrés atomiquement.
