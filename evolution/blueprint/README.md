---
id: BPT-001
title: Product Blueprint
status: In Review
owner: Product
version: 1.3.0
last_updated: 2026-08-06

references:
  - modules.md
  - product-map.md
  - user-journeys.md
  - lifecycle.md
  - dashboard.md
  - navigation.md
  - permissions.md
  - public-api.md
  - integrations.md
  - implementation-plan.md
  - roadmap.md
  - ../roadmap/mvp-scope.md
  - ../roadmap/mvp-acceptance.md
  - ../reference-fixtures/README.md
  - ../../fondation/README.md
  - ../../fondation/decisions/ADR-001-mvp-application-topology.md
  - ../../fondation/security/mvp-threat-model.md
---

# Product Blueprint

Le Product Blueprint décrit la forme exécutable du MVP Atlas. Il relie la
vision, les bounded contexts consolidés et l'ordre de construction sans
dupliquer leurs règles métier.

En cas de contradiction, la hiérarchie de
[`fondation/`](../../fondation/README.md) s'applique. Les documents de domaine
restent propriétaires des commandes, événements, permissions, invariants et
erreurs. Le Blueprint en montre l'assemblage.

---

## Parcours de lecture

1. [`modules.md`](modules.md) — bounded contexts et surfaces applicatives ;
2. [`product-map.md`](product-map.md) — dépendances et propriétaires ;
3. [`user-journeys.md`](user-journeys.md) — trois parcours MVP ;
4. [`mvp-acceptance.md`](../roadmap/mvp-acceptance.md) — preuve de bout en bout ;
5. [`implementation-plan.md`](implementation-plan.md) — ordre de construction ;
6. [`dashboard.md`](dashboard.md) et [`navigation.md`](navigation.md) —
   composition visible par l'utilisateur ;
7. [`permissions.md`](permissions.md), [`public-api.md`](public-api.md) et
   [`integrations.md`](integrations.md) — frontières d'exécution ;
8. [`roadmap.md`](roadmap.md) — séquencement produit après le MVP ;
9. [`reference-fixtures/`](../reference-fixtures/README.md) — entrées et
   résultats exécutables de la chaîne de décision.

---

## Décisions structurantes du MVP

- le MVP couvre Identity, Workspace, CRM, Billing, Analytics, Business Health,
  Advisor et Notifications ;
- sa topologie de départ est le modular monolith à frontières fortes accepté
  dans `ADR-001` ;
- Analytics est un moteur interne nécessaire au résultat, pas nécessairement
  un module de navigation autonome ;
- le Dashboard est une composition de read models, pas un bounded context ;
- Identity reste l'autorité unique des permissions effectives ;
- les actions Advisor et Dashboard sont exécutées par le domaine propriétaire ;
- les e-mails Identity, les documents Billing et les notifications Advisor
  utilisent des ports de livraison distincts ;
- `Automation`, `Projects`, les connecteurs produit et l'API publique externe
  sont différés après la preuve du MVP.

---

## Condition de stabilité

Le Blueprint peut passer de `In Review` à `Stable` après :

1. validation produit des trois parcours et des états UX ;
2. validation Engineering que le Blueprint respecte `ADR-001` ;
3. validation Security du modèle de menace et des preuves publiques ;
4. passage du contrôle `scripts/check-mvp-blueprint-docs.sh` ;
5. passage du contrôle `scripts/check-mvp-reference-fixtures.sh` ;
6. absence de contradiction avec les checkers des huit bounded contexts.
