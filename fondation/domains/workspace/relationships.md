---
id: WSP-RELATIONSHIPS
title: Workspace Relationships
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - scope.md
  - integrations.md
  - ../identity/api.md
---

# Relations

## Relations internes

```text
Workspace 1 --- 1 WorkspaceProfile
Workspace 1 --- 1 BillingIdentity
Workspace 1 --- 1 WorkspacePreferences
Workspace 1 --- 0..1 RestrictionContext
Workspace 1 --- 0..1 ClosureContext
```

Ces relations sont contenues dans l'agrégat et ne possèdent pas d'identité
indépendante.

---

## Relations externes

| Domaine | Relation | Propriétaire |
|---|---|---|
| `Identity` | plusieurs `Membership` peuvent référencer un `WorkspaceId` | Identity |
| `CRM` | chaque ressource est contextualisée par un `WorkspaceId` | CRM |
| `Billing` | chaque document est contextualisé et peut copier un snapshot | Billing |
| `Advisor` | une recommandation est contextualisée | Advisor |

Workspace ne conserve pas la collection inverse de ces références.

---

## Règle de navigation

Une relation externe se résout par :

- un contrat de lecture public ;
- une projection locale alimentée par événements ;
- ou une commande publique lorsqu'une modification est demandée.

La navigation directe entre modèles de stockage est interdite.

---

## Relation avec l'owner

Workspace publie `RequiresActiveOwner = true` en 1.0. `Identity` reste
propriétaire de la définition du rôle owner et de l'invariant portant sur les
memberships.

Workspace exige seulement une preuve booléenne et versionnée de readiness pour
les transitions qui rendent l'espace utilisable. Il ne copie ni le rôle, ni la
liste des owners.
