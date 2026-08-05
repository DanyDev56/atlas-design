---
id: WSP-GLOSSARY
title: Workspace Glossary
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

# Glossaire Workspace

| Terme | Définition |
|---|---|
| `Workspace` | activité professionnelle utilisant Atlas et contexte d'isolation de ses données |
| `WorkspaceProfile` | identité commerciale courante destinée à l'affichage et à la réutilisation |
| `BillingIdentity` | coordonnées administratives déclarées servant de source aux futurs snapshots financiers |
| `WorkspacePreferences` | locale, fuseau, devise et pays utilisés comme valeurs par défaut |
| `WorkspaceStatus` | état interne du cycle de vie |
| `WorkspaceAccessContext` | projection minimale utilisée pour décider si le contexte est utilisable |
| `OwnerReadinessProof` | confirmation bornée d'Identity qu'un owner actif existe |
| `Provisioning` | état précédant la fin du bootstrap |
| `Restricted` | état suspendant l'usage ordinaire sans fermeture |
| `Closed` | état logique terminal du Workspace |

## Termes à ne pas substituer à Workspace

- Company ;
- Organisation ;
- Tenant ;
- Account.

Ces mots peuvent exister dans un contexte légal ou technique spécifique, mais
ne nomment jamais le concept métier `Workspace`.
