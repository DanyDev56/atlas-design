---
id: BPT-012
title: Product Delivery Roadmap
status: In Review
owner: Product
version: 1.4.0
last_updated: 2026-08-23

references:
  - README.md
  - implementation-plan.md
  - ../roadmap/mvp-scope.md
  - ../roadmap/mvp-acceptance.md
  - ../../fondation/decisions/ADR-001-mvp-application-topology.md
  - ../../fondation/decisions/ADR-002-mvp-implementation-stack.md
  - ../../fondation/security/mvp-threat-model.md
  - ../../fondation/product/pricing-strategy.md
---

# Roadmap de livraison

Cette roadmap exprime un ordre de réduction du risque, pas des dates. Un palier
ne s'ouvre que lorsque son gate de sortie est prouvé.

---

## Palier 0 — Clôture de conception du MVP

- valider les trois parcours et états UX ;
- appliquer la topologie acceptée dans `ADR-001` ;
- exécuter le spike des douze conditions de `ADR-002`, puis accepter ou réviser
  la stack proposée ;
- valider le modèle de menace `SEC-001` et préparer les fixtures de référence ;
- rendre les quality gates exécutables.

Gate : Blueprint `Stable`, checker vert, modèle de menace validé et décisions
de topologie et de stack bloquantes acceptées.

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
- beta fermée, mesure des outcomes et corrections ;
- validation du packaging `Atlas Solo`, de la disposition à payer et de
  l'économie unitaire, sans encaissement tant que le gate commercial n'est pas
  satisfait.

Gate : Definition of Done MVP satisfaite et risques résiduels acceptés.

### Gate commercial distinct

La disponibilité technique du MVP n'autorise pas à elle seule sa vente. Le gate
commercial exige la validation du prix, un contexte propriétaire pour
Subscription et Entitlement, un checkout sécurisé, un cycle d'essai complet,
la résiliation, les factures d'abonnement, le support et les mesures de marge.
La checklist exhaustive appartient à la
[stratégie tarifaire](../../fondation/product/pricing-strategy.md).

## Palier 4 — Extension validée

Prioriser à partir des usages observés, sans ordre prédéfini :

- Projects ;
- Automation bornée ;
- premier connecteur produit ;
- API publique externe ;
- capacités Analytics avancées ;
- découverte d'une éventuelle offre `Atlas Équipe`, uniquement après preuve de
  valeur collaborative.

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
