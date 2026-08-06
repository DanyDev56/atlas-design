---
id: BPT-012
title: Product Delivery Roadmap
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-06

references:
  - README.md
  - implementation-plan.md
  - ../roadmap/mvp-scope.md
  - ../roadmap/mvp-acceptance.md
---

# Roadmap de livraison

Cette roadmap exprime un ordre de réduction du risque, pas des dates. Un palier
ne s'ouvre que lorsque son gate de sortie est prouvé.

---

## Palier 0 — Clôture de conception du MVP

- valider les trois parcours et états UX ;
- confirmer la topologie d'implémentation par ADR ;
- préparer le modèle de menace et les fixtures de référence ;
- rendre les quality gates exécutables.

Gate : Blueprint `Stable`, checker vert et décisions techniques bloquantes
acceptées.

## Palier 1 — Contexte sûr et gestion commerciale

- socle d'exécution ;
- Identity et Workspace ;
- CRM ;
- Billing jusqu'au paiement.

Gate : `MVP-J1` et `MVP-J2` passent de bout en bout, y compris reprise et
isolation.

## Palier 2 — Compréhension et décision

- Analytics comme moteur interne ;
- Business Health ;
- Advisor ;
- Notifications ;
- Dashboard de composition.

Gate : `MVP-J3` passe avec données suffisantes, données insuffisantes, zéro
Recommendation et notification éligible/non éligible.

## Palier 3 — Publication contrôlée

- sécurité, accessibilité et performance ;
- migrations, sauvegarde, restauration et reconstruction ;
- observabilité, alertes, runbooks et support ;
- beta fermée, mesure des outcomes et corrections.

Gate : Definition of Done MVP satisfaite et risques résiduels acceptés.

## Palier 4 — Extension validée

Prioriser à partir des usages observés, sans ordre prédéfini :

- Projects ;
- Automation bornée ;
- premier connecteur produit ;
- API publique externe ;
- capacités Analytics avancées.

Chaque extension exige sa propre découverte, ses frontières de domaine et une
preuve qu'elle améliore une décision utilisateur.

## Palier 5 — Plateforme

- multi-workspaces ;
- marketplace ;
- mobile natif ;
- IA avancée et assistant conversationnel ;
- écosystème d'extensions.

Ces éléments restent hypothétiques tant que les paliers précédents n'ont pas
validé la boucle de valeur d'Atlas.
