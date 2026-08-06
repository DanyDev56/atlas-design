---
id: BPT-006
title: MVP Product Map
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-06

references:
  - README.md
  - modules.md
  - dashboard.md
  - ../../fondation/domain-map/context-map.md
  - ../../fondation/domain-map/dependencies.md
  - ../../fondation/domain-map/ownership.md
---

# Carte produit du MVP

```text
                        Atlas application

          Identity <==== partnership ====> Workspace
              |                               |
              +---------------+---------------+
                              |
                              v
CRM ---------> Billing ------> Analytics ------> Business Health
 |                 |                                  |
 +<-- QuoteAccepted orchestration                     v
                                                   Advisor
                                                      |
                                                      v
                                                Notifications

Dashboard ----------------------------------------------------+
  reads public views from CRM, Billing, Analytics,             |
  Business Health, Advisor and Notifications                   |
  under Identity authorization and Workspace isolation <------+
```

Une flèche représente la consommation d'un contrat public. Elle n'autorise
jamais une lecture de stockage ni une mutation du modèle source.

---

## Propriété

| Concept affiché | Propriétaire unique |
|---|---|
| principal, membership, rôle, permission effective | Identity |
| profil, identité de facturation courante, état d'accès | Workspace |
| client, contact, opportunity, pipeline | CRM |
| quote, invoice, payment, credit note, document financier | Billing |
| fait analytique, métrique, snapshot, fraîcheur | Analytics |
| score, facteur, risque, attention principale | Business Health |
| recommendation, priorité, preuve, action proposée | Advisor |
| notification, état lu, préférence, delivery | Notifications |

Le Dashboard, l'onboarding, Settings et la navigation n'ajoutent aucun concept
propriétaire à cette table.

---

## Règles de lecture transverses

- Identity autorise, mais le domaine propriétaire applique encore ses
  invariants ;
- Workspace borne toutes les données métier et peut en restreindre l'accès ;
- les documents Billing figent des snapshots sans transférer la propriété des
  profils sources ;
- la chaîne Analytics vers Notifications est versionnée, explicable et
  reconstructible ;
- le Dashboard peut mettre en cache une composition, jamais remplacer les vues
  sources.

`Projects`, `Automation`, connecteurs produit et API publique externe sont
absents de cette carte MVP.
