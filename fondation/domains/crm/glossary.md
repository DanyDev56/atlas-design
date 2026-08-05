---
id: CRM-GLOSSARY
title: CRM Glossary
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - model.md
  - value-objects.md
  - ../../language/glossary.md
---

# Glossaire CRM

| Terme | Définition |
|---|---|
| `Client` | contrepartie identifiée d'une relation commerciale potentielle ou établie |
| `Contact` | personne rattachée à un Client et joignable dans cette relation |
| `Opportunity` | possibilité réelle de conclure une vente avec un Client |
| `Activity` | interaction commerciale passée enregistrée dans CRM |
| `Pipeline` | projection des Opportunity selon leur statut courant |
| `ClientProfile` | profil commercial courant du Client |
| `ClientBillingProfile` | données administratives courantes fournies à Billing comme source de snapshot |

## Termes non retenus comme concepts

- Lead ;
- Prospect ;
- Deal ;
- Account ;
- Company.

Ces termes peuvent apparaître dans une citation ou un système externe, mais ne
remplacent jamais `Client` ou `Opportunity` dans le modèle Atlas.

Un Client potentiel n'est pas un type d'entité séparé. C'est la présence d'une
Opportunity non terminale qui exprime la vente potentielle.
