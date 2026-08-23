---
id: GOV-001
title: Documentation Consolidation Matrix
status: In Review
owner: Product
version: 1.6.0
last_updated: 2026-08-23

references:
  - quality-gates.md
  - ../roadmap/mvp-scope.md
  - ../roadmap/mvp-acceptance.md
  - ../blueprint/README.md
  - ../reference-fixtures/README.md
  - ../../fondation/domain-map/README.md
  - ../../fondation/decisions/README.md
  - ../../fondation/decisions/ADR-001-mvp-application-topology.md
  - ../../fondation/decisions/ADR-002-mvp-implementation-stack.md
  - ../../fondation/security/README.md
  - ../../fondation/security/mvp-threat-model.md
  - ../../fondation/product/pricing-strategy.md
---

# Matrice de consolidation

Cette matrice suit l’état de consolidation de la documentation Atlas.

| Zone | Document | Présent | Normalisé | Cohérent | Complet | Statut cible |
|---|---|---:|---:|---:|---:|---|
| Vision | Mission | Oui | Oui | Oui | Oui | Stable |
| Vision | Vision | Oui | Oui | Oui | Oui | Stable |
| Vision | Principles | Oui | Oui | Oui | Oui | Stable |
| Vision | Anti-goals | Oui | Oui | Oui | Oui | Stable |
| Vision | Constitution | Oui | Oui | Oui | Oui | Stable |
| Product | Product Strategy | Oui | Non | À vérifier | Partiel | In Review |
| Product | Pricing Strategy | Oui | Oui | Oui | Partiel | Draft |
| Product | Primary Persona | Oui | Non | À vérifier | Oui | In Review |
| Product | Secondary Persona | Non | Non | Non | Non | Draft |
| Product | Anti-personas | Oui | Non | À vérifier | Oui | In Review |
| Product | Jobs To Be Done | Oui | Non | À vérifier | Partiel | In Review |
| Domains | Billing | Oui | Oui | Oui | Oui | In Review |
| Domains | CRM | Oui | Oui | Oui | Oui | In Review |
| Domains | Analytics | Oui | Oui | Oui | Oui | In Review |
| Domains | Advisor | Oui | Oui | Oui | Oui | In Review |
| Domains | Business Health | Oui | Oui | Oui | Oui | In Review |
| Domains | Identity | Oui | Oui | Oui | Oui | In Review |
| Domains | Workspace | Oui | Oui | Oui | Oui | In Review |
| Domains | Notifications | Oui | Oui | Oui | Oui | In Review |
| Domains | Automation | Non | Non | Non | Non | Draft |
| Language | Glossary | Oui | Oui | À vérifier | Partiel | In Review |
| Domain Map | Context Map | Oui | Non | Oui | Oui | In Review |
| Blueprint | Product Blueprint | Oui | Oui | Oui | Oui | In Review |
| Roadmap | MVP Scope | Oui | Oui | Oui | Oui | In Review |
| Roadmap | MVP End-to-End Acceptance | Oui | Oui | Oui | Oui | In Review |
| Delivery | MVP Implementation Plan | Oui | Oui | Oui | Oui | In Review |
| Delivery | MVP Reference Fixtures | Oui | Oui | Oui | Oui | In Review |
| Decisions | ADR catalogue | Oui | Oui | Oui | Oui | Living Document |
| Decisions | MVP Implementation Stack | Oui | Oui | Oui | Oui | Accepted |
| Security | MVP Threat Model | Oui | Oui | Oui | Oui | In Review |

---

## Prochain gate documentaire

La consolidation des bounded contexts et du Blueprint permet de commencer le
palier 0 d'implémentation. Le passage à `Stable` reste bloqué par :

- la validation produit des parcours et états UX ;
- la validation de la disposition à payer et du packaging `Atlas Solo` ;
- la validation Security du modèle de menace transversal ;
- l'acceptation d'`ADR-002` après le spike de compatibilité et de frontières — **faite** ;
- l'acceptation formelle des quality gates par Engineering et Security.

Le profil `BusinessHealthBaselineV1@1.0.0` et ses seuils de fraîcheur sont
maintenant contractuels et vérifiés par les fixtures. Il ne bloque plus
l'implémentation du contrat de publication Analytics. Ces seuils portent sur le
retard de traitement d'événements attendus ; ils ne rendent jamais obsolète un
Workspace simplement parce qu'aucune nouvelle activité n'y a été enregistrée.

`Automation` reste volontairement absent : son cadrage ne constitue pas un
prérequis du MVP.

La stratégie tarifaire reste `Draft` tant que le prix n'a pas été confronté à
des décisions réelles et que le contexte propriétaire de Subscription et
Entitlement n'a pas fait l'objet d'une décision structurante.
