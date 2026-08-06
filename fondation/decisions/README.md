---
id: ADR-CATALOG
title: Architecture Decision Record Catalogue
status: Living Document
owner: Product and Engineering
version: 1.2.0
last_updated: 2026-08-06

references:
  - template.md
  - ADR-001-mvp-application-topology.md
  - ADR-002-mvp-implementation-stack.md
  - ../README.md
---

# Catalogue des décisions structurantes

## Objectif

Un ADR conserve le contexte, les options, la décision, ses raisons et ses
conséquences. Il évite qu'une contrainte structurante soit oubliée ou rediscutée
sans nouvel élément.

Ce catalogue recense les décisions transverses de la Fondation. Les décisions
propres à un bounded context restent dans son `decision-record.md` et utilisent
son préfixe, par exemple `CRM-ADR-*` ou `BIL-ADR-*`.

---

## Catalogue

| ID | Décision | Statut | Date | Owner |
|---|---|---|---|---|
| [`ADR-001`](ADR-001-mvp-application-topology.md) | Le MVP utilise un modular monolith à frontières fortes. | Accepted | 2026-08-06 | Engineering |
| [`ADR-002`](ADR-002-mvp-implementation-stack.md) | Le MVP propose PHP, Laravel, React et PostgreSQL. | Proposed | 2026-08-06 | Engineering |

---

## Statuts

| Statut | Sens |
|---|---|
| `Proposed` | Décision ouverte à la revue ; elle n'est pas encore normative. |
| `Accepted` | Décision approuvée et normative selon la hiérarchie de la Fondation. |
| `Rejected` | Option étudiée mais non retenue. |
| `Deprecated` | Décision encore historique mais déconseillée pour tout nouvel usage. |
| `Superseded` | Décision remplacée ; le nouvel ADR est référencé explicitement. |

Un ADR accepté n'est jamais réécrit pour inverser sa décision. Un changement de
direction crée un nouvel ADR avec les liens `supersedes` et `superseded_by`
appropriés.

---

## Numérotation et nommage

- les décisions transverses utilisent `ADR-NNN` ;
- le fichier utilise `ADR-NNN-short-kebab-title.md` ;
- un numéro n'est jamais réutilisé ;
- l'identifiant reste stable après renommage du titre ;
- les décisions de domaine conservent leur propre catalogue et préfixe.

---

## Ce qui mérite un ADR

- topologie, frontières ou propriété des données ;
- stratégie de persistence, messaging, sécurité ou déploiement ;
- langage, framework ou infrastructure difficiles à remplacer ;
- contrat externe ou intégration structurante ;
- changement durable de modèle métier ou de promesse produit ;
- compromis qui impose une contrainte à plusieurs équipes ou domaines.

Une modification locale et facilement réversible ne nécessite pas d'ADR.

---

## Processus

1. copier [`template.md`](template.md) et attribuer le prochain numéro ;
2. documenter les forces de décision et les options réellement considérées ;
3. marquer le document `Proposed` pendant la revue ;
4. enregistrer la décision et ses owners ;
5. passer à `Accepted` ou `Rejected` ;
6. mettre à jour ce catalogue et les documents affectés ;
7. ajouter des contrôles exécutables lorsque la décision impose une règle
   vérifiable.

Le contrôle documentaire est exécuté avec :

```bash
scripts/check-decisions-docs.sh
```
