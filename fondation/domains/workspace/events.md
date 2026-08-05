---
id: WSP-EVENTS
title: Workspace Domain Events
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - invariants.md
  - commands/README.md
  - integrations.md
---

# Domain Events

Un événement décrit un fait déjà commis par Workspace. Il ne constitue jamais
une commande implicite pour un consommateur.

## Enveloppe commune

| Champ | Description |
|---|---|
| `EventId` | identifiant unique |
| `EventName` | nom canonique |
| `SchemaVersion` | version du schéma |
| `OccurredAt` | instant du fait |
| `WorkspaceId` | agrégat concerné |
| `AggregateVersion` | révision après le fait |
| `CorrelationId` | workflow transversal |
| `CausationId` | commande ou fait causal |
| `ActorReference` | acteur auditable |
| `Data` | charge utile minimale |

Les événements publics ne contiennent ni adresse complète, ni identifiant
fiscal brut, ni note de conformité, ni secret.

---

## Catalogue canonique

| Événement | Producteur | Fait minimum |
|---|---|---|
| `WorkspaceCreated` | `CreateWorkspace` | Workspace créé en `Provisioning`. |
| `WorkspaceActivated` | `ActivateWorkspace` | Bootstrap terminé et usage ordinaire autorisé. |
| `WorkspaceProfileUpdated` | `UpdateWorkspaceProfile` | Nouvelle version du profil disponible. |
| `WorkspaceBillingIdentityUpdated` | `UpdateWorkspaceBillingIdentity` | Nouvelle version de l'identité de facturation disponible. |
| `WorkspacePreferencesChanged` | `ChangeWorkspacePreferences` | Nouvelle version des préférences disponible. |
| `WorkspaceRestricted` | `RestrictWorkspace` | Usage ordinaire suspendu. |
| `WorkspaceAccessRestored` | `RestoreWorkspaceAccess` | Restriction levée et usage ordinaire rétabli. |
| `WorkspaceClosed` | `CloseWorkspace` | Workspace fermé logiquement et définitivement. |
| `WorkspaceAccessStateChanged` | commandes de cycle de vie | État d'accès public et version de gouvernance modifiés. |

`WorkspaceAccessStateChanged` contient uniquement :

```text
WorkspaceId
PreviousAccessState
AccessState
GovernanceVersion
RequiresActiveOwner
```

Sur la première activation, `PreviousAccessState = Restricted`. Une restriction
du statut `Active` produit `Active -> Restricted`; une fermeture produit la
transition depuis l'état public courant vers `Closed`.

---

## Événements de profil

Les événements de mise à jour contiennent la version, les catégories de champs
modifiées et, si nécessaire, une référence de lecture autorisée. Ils ne
dupliquent pas les données sensibles du profil.

Un consommateur récupère la valeur courante par API. S'il exige une cohérence
forte avec une version donnée, il demande explicitement cette version ou reprend
son traitement.

---

## Publication

- l'état et les événements sont atomiques ;
- les effets externes passent par une outbox ;
- les consommateurs sont idempotents par `EventId` ;
- l'ordre est garanti par `AggregateVersion` pour un même Workspace ;
- un refus avant commit ne produit aucun événement de réussite.
