---
id: SEC-README
title: Atlas Security Foundation
status: In Review
owner: Product, Engineering and Security
version: 1.0.0
last_updated: 2026-08-06

references:
  - mvp-threat-model.md
  - ../constitution.md
  - ../decisions/ADR-001-mvp-application-topology.md
  - ../../evolution/governance/quality-gates.md
---

# Fondation Security

## Objectif

Ce dossier décrit les exigences de sécurité transverses d'Atlas. Il ne remplace
ni les invariants possédés par les bounded contexts, ni une revue juridique, ni
les procédures opérationnelles d'un environnement concret.

La sécurité suit la même hiérarchie que le reste de la Fondation :

- la Constitution fixe les principes non négociables ;
- les ADR fixent les contraintes structurantes ;
- les domaines possèdent leurs autorisations, preuves et invariants ;
- le modèle de menace relie les frontières aux scénarios d'abus ;
- les quality gates exigent des preuves d'implémentation.

---

## Documents

| Document | Rôle | Statut |
|---|---|---|
| [`mvp-threat-model.md`](mvp-threat-model.md) | Périmètre, actifs, frontières, menaces, contrôles, tests et risques ouverts du MVP. | In Review |

---

## Règles de gouvernance

- aucune menace n'est considérée traitée avant vérification du contrôle dans un
  environnement représentatif ;
- un risque résiduel `High` ou `Critical` bloque la publication tant qu'il n'est
  pas réduit ou accepté formellement par un owner identifié ;
- une acceptation de risque possède une échéance et des conditions de réexamen ;
- chaque changement de frontière, flux sensible, fournisseur, authentification
  ou exposition publique met à jour le modèle ;
- un incident réel déclenche une revue des hypothèses, contrôles, détections et
  tests concernés ;
- les détails exploitables, secrets et indicateurs de compromission restent
  dans un canal restreint, pas dans ce dépôt de conception général.

---

## Validation

Le contrôle documentaire est exécuté avec :

```bash
scripts/check-security-docs.sh
```

Ce contrôle vérifie la traçabilité documentaire. Les tests de sécurité définis
dans le modèle devront devenir exécutables avec l'application ; le checker ne
prouve pas leur efficacité.
