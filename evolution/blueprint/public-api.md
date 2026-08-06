---
id: BPT-008
title: Contract and API Exposure
status: In Review
owner: Product and Engineering
version: 1.2.0
last_updated: 2026-08-06

references:
  - README.md
  - permissions.md
  - integrations.md
  - ../roadmap/mvp-scope.md
  - ../../fondation/domain-map/dependencies.md
  - ../../fondation/domains/identity/api.md
  - ../../fondation/domains/workspace/api.md
  - ../../fondation/domains/crm/api.md
  - ../../fondation/domains/billing/api.md
  - ../../fondation/domains/analytics/api.md
  - ../../fondation/domains/business-health/api.md
  - ../../fondation/domains/advisor/api.md
  - ../../fondation/domains/notifications/api.md
  - ../../fondation/security/mvp-threat-model.md
---

# Contrats et exposition API

## Sens de « public » dans le MVP

Un contrat public de bounded context est utilisable à sa frontière. Il ne
signifie pas que cette capacité est accessible depuis Internet ou ouverte à une
application tierce.

Les contrats de domaine sont indépendants du transport :

```text
intent or query
  -> authentication and authorization adapter
  -> public domain contract
  -> domain result or stable error
  -> transport representation
```

HTTP, message asynchrone et appel dans un même processus peuvent adapter le même
contrat sans modifier son intention ou ses garanties.

---

## Surface requise par le MVP

| Groupe | Exposition |
|---|---|
| Identity et Workspace | adaptateurs first-party pour l'application et contrats de confiance pour le bootstrap. |
| CRM et Billing | commandes et lectures first-party ; vues publiques de document accessibles uniquement avec preuve bornée. |
| Import initial | upload first-party isolé, aperçu sans mutation, puis intentions CRM/Billing confirmées et auditables. |
| Analytics à Notifications | lectures utilisateur first-party et contrats système authentifiés entre workloads. |
| Dashboard | composition côté application ou backend-for-frontend à partir des lectures publiques existantes. |
| Domain Events | enveloppes versionnées livrées à des consommateurs allowlistés, jamais un flux public générique. |

Les vues publiques Quote/Invoice ne constituent pas une API tierce. Leur preuve
est opaque, expirée selon politique, bornée au document et sans pouvoir sur le
Workspace.

---

## Conventions de frontière

- commandes avec `WorkspaceId` lorsque pertinent, clé d'idempotence et version
  attendue pour les mutations concurrentes ;
- lectures paginées et bornées, sans effet métier ;
- catégories d'erreur stables séparées du statut d'un transport ;
- authentification du principal ou du workload et permission exacte ;
- versions de contrat explicites, ajouts compatibles et dépréciation annoncée ;
- aucune credential, preuve publique ou donnée personnelle sensible dans un
  événement ou un log ordinaire ;
- quotas, taille maximale, timeout et corrélation définis dans l'adaptateur.
- un fichier importé n'est jamais interprété comme une commande avant scan,
  mapping canonique, aperçu et confirmation humaine.

Un adaptateur REST éventuel peut utiliser OpenAPI. Cette décision ne transforme
pas REST, OAuth2 ou les webhooks en contraintes universelles du domaine.

---

## API publique externe — post-MVP

L'ouverture à des applications tierces exige avant publication :

1. un modèle d'application cliente et de consentement ;
2. des scopes distincts des templates de rôles humains ;
3. OAuth2/OIDC et rotation des credentials selon le modèle de menace retenu ;
4. quotas, révocation, audit, support et conditions d'usage ;
5. politique de versioning et de dépréciation appliquée ;
6. webhooks signés, filtrés, rejouables et sans secrets ;
7. revue de minimisation des données pour chaque ressource ;
8. tests d'isolation multi-tenant et programme de réponse aux incidents.

Avant ces décisions, aucune route `/clients`, `/invoices`, `/events` ou autre ne
doit être annoncée comme API publique externe.
