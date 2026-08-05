---
id: WSP-INTEGRATIONS
title: Workspace Integrations
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - api.md
  - events.md
  - workflows.md
  - ../identity/api.md
  - ../identity/integrations.md
---

# Intégrations

## Identity

### Workspace fournit

- `getWorkspaceAccessContext` ;
- `WorkspaceAccessStateChanged`.

Identity utilise ce contrat pour refuser les autorisations dans un espace en
provisioning, restreint ou fermé et pour invalider ses décisions en cache.

### Workspace consomme

```text
getWorkspaceOwnerReadiness(workspaceId)
→
WorkspaceId
HasActiveOwner
IdentityGovernanceVersion
AssessedAt
ValidUntil
```

Cette lecture est réservée au bootstrap et aux workflows de gouvernance. Elle ne
révèle aucun `UserId`, `MembershipId` ou `RoleId`.

Workspace consomme également le service d'autorisation Identity pour les
commandes initiées par un membre.

---

## Billing

Billing peut lire :

- `BillingIdentity` et sa version ;
- les préférences courantes nécessaires à une valeur par défaut ;
- l'état d'accès lorsque sa politique l'exige.

Billing reste propriétaire :

- de la validation préalable à l'émission ;
- du snapshot enregistré dans le devis ou la facture ;
- des taxes, numéros, échéances, paiements et corrections ;
- des comportements légaux après fermeture du Workspace.

Workspace ne consomme aucun événement Billing pour maintenir son agrégat.

---

## CRM et autres domaines métier

Les domaines utilisent `WorkspaceId` comme frontière d'isolation et peuvent
lire un résumé minimal. Ils ne copient une donnée de profil que si leur propre
historique l'exige.

Un changement de nom n'entraîne pas le renommage destructif de tous les faits
passés.

---

## Catalogue de référence

La validation des codes de locale, fuseau, devise et pays passe par un port de
référence versionné. Une indisponibilité ne transforme pas une valeur non
vérifiée en valeur valide.

Les mises à jour de catalogue suivent une migration explicite et conservent la
capacité de lire les valeurs antérieures.

---

## Audit et conformité

Les décisions de restriction et fermeture alimentent un journal append-only
restreint. Les événements publics exposent une catégorie minimale, jamais les
notes sensibles ou pièces justificatives.

La rétention, l'export et l'effacement sont déclenchés après les faits métier et
respectent la propriété de chaque domaine.

---

## Garanties communes

- appels idempotents ;
- timeouts bornés ;
- retries avec backoff ;
- outbox transactionnelle ;
- consommateurs dédupliqués par `EventId` ;
- corrélation de bout en bout ;
- refus par défaut en cas d'incertitude d'accès ;
- aucune base de données partagée entre contextes.
