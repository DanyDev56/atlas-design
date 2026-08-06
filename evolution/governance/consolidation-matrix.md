---
id: GOV-001
title: Documentation Consolidation Matrix
status: In Review
owner: Product
version: 1.3.0
last_updated: 2026-08-06

references:
  - quality-gates.md
  - ../roadmap/mvp-scope.md
  - ../roadmap/mvp-acceptance.md
  - ../blueprint/README.md
  - ../reference-fixtures/README.md
  - ../../fondation/domain-map/README.md
  - ../../fondation/decisions/README.md
  - ../../fondation/decisions/ADR-001-mvp-application-topology.md
  - ../../fondation/security/README.md
  - ../../fondation/security/mvp-threat-model.md
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
| Security | MVP Threat Model | Oui | Oui | Oui | Oui | In Review |

---

## Prochain gate documentaire

La consolidation des bounded contexts et du Blueprint permet de commencer le
palier 0 d'implémentation. Le passage à `Stable` reste bloqué par :

- la validation produit des parcours et états UX ;
- la validation Security du modèle de menace transversal ;
- les décisions de stack compatibles avec `ADR-001` ;
- l'acceptation formelle des quality gates par Engineering et Security.

Le gap `analytics.snapshot-profile-version-unassigned` reste visible dans les
fixtures et doit être résolu avant l'implémentation du contrat de publication
Analytics ; aucune version implicite n'est admise.

`Automation` reste volontairement absent : son cadrage ne constitue pas un
prérequis du MVP.
