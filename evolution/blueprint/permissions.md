---
id: BPT-007
title: MVP Permission Composition
status: In Review
owner: Product and Security
version: 1.1.0
last_updated: 2026-08-23

references:
  - README.md
  - navigation.md
  - backoffice.md
  - ../../fondation/domains/identity/permissions.md
  - ../../fondation/domains/workspace/permissions.md
  - ../../fondation/domains/crm/permissions.md
  - ../../fondation/domains/billing/permissions.md
  - ../../fondation/domains/analytics/permissions.md
  - ../../fondation/domains/business-health/permissions.md
  - ../../fondation/domains/advisor/permissions.md
  - ../../fondation/domains/notifications/permissions.md
  - ../../fondation/decisions/ADR-004-operator-control-plane.md
---

# Composition des permissions du MVP

## Autorité unique

Identity possède le catalogue global, les rôles, leurs affectations et la
décision d'autorisation contextualisée. Chaque domaine possède le sens de ses
clés et vérifie ses invariants après l'autorisation.

```text
Active User
  + Active Membership
  + Active Role
  + Active Workspace
  + effective PermissionKey
  + resource and policy checks
  -> Allowed | Denied
```

Un rôle n'est jamais une permission. Les noms `Owner`, `Admin`, `Member` ou
`Viewer` ne permettent donc aucune décision implicite dans l'interface, une API
ou un domaine.

## Autorité opérateur proposée

L'autorité Workspace ci-dessus ne peut pas autoriser `/backoffice`. `ADR-004`
propose une audience et des grants plateforme séparés :

```text
Active User
  + Operator Session Audience
  + Active, provisioned OperatorGrant
  + exact operations PermissionKey
  + object, sensitivity, step-up and approval checks
  -> Allowed | Denied
```

Un grant opérateur n'est créé ni par une invitation Workspace, ni par le rôle
Owner, ni par une variable frontend. Les clés proposées, profils minimaux et
règles de double approbation sont catalogués dans
[`backoffice.md`](backoffice.md). Tant que `ADR-004` n'est pas accepté, elles ne
sont pas ajoutées au catalogue Identity exécutable.

---

## Rôles initiaux

Le bootstrap peut créer des templates `Owner` et `Member`, mais leur contenu est
une politique produit versionnée composée de clés canoniques.

Le rôle dont `RoleSystemType = Owner` conserve :

- le socle Identity obligatoire défini par Identity ;
- les permissions owner obligatoires déclarées par chaque domaine MVP ;
- aucune capacité `SystemActorOnly`.

Le template `Member` n'est pas une promesse d'accès universel. Il reçoit
uniquement les clés explicitement prévues par la version du template. Toute
modification produit du template est auditée, idempotente et compatible avec
les rôles déjà personnalisés selon une politique de migration explicite.

---

## Capacités par surface

| Surface | Capacités de lecture typiques | Mutations possibles, toujours explicites |
|---|---|---|
| Dashboard | permissions `read` de chaque widget affiché | aucune mutation possédée par Dashboard. |
| CRM | `crm.clients.read`, `crm.contacts.read`, `crm.opportunities.read`, `crm.activities.read` | clés exactes `create`, `update`, `qualify`, `win`, `lose`, etc. |
| Billing | `billing.quotes.read`, `billing.invoices.read`, `billing.payments.read`, `billing.credit-notes.read` | clés exactes de création, émission, envoi, paiement et correction. |
| Business Health | `business-health.assessments.read` | aucune mutation utilisateur de l'assessment. |
| Advisor | `advisor.recommendations.read` | `advisor.recommendations.complete` ou `advisor.recommendations.dismiss`. |
| Inbox | `notifications.inbox.read`, `notifications.preferences.read` | `notifications.inbox.mark-read`, `notifications.preferences.change`. |
| Settings | clés Identity et Workspace correspondant à chaque écran | aucune permission générique `settings.manage`. |

Cette table oriente la composition UI ; les catalogues de domaine référencés en
front matter restent normatifs.

---

## Règles d'interface

- une route ne déduit jamais l'autorité depuis le nom du rôle ;
- le masquage d'un contrôle évite une fausse affordance, mais le serveur refuse
  encore par défaut ;
- une vue composite demande chaque capacité requise et tolère l'absence d'un
  widget non autorisé ;
- le changement de rôle, de membership, de User ou d'état Workspace invalide
  immédiatement les décisions mises en cache ;
- les actions `Elevated` et `Critical` appliquent les preuves, step-up,
  approbations et contraintes documentées par leur domaine ;
- une capacité `SystemActorOnly` n'est jamais accordée à un rôle de Workspace.

---

## Tests d'acceptation

Pour chaque intention du MVP, vérifier au minimum :

1. acteur autorisé dans le bon Workspace ;
2. acteur authentifié sans la permission exacte ;
3. acteur autorisé dans un autre Workspace ;
4. Membership, Role ou Workspace devenu inactif ;
5. décision mise en cache avant une réduction de privilèges ;
6. tentative humaine d'utiliser une capacité système.

Un refus ne révèle ni la présence de la ressource, ni la structure du rôle, ni
le détail sensible de la politique.
