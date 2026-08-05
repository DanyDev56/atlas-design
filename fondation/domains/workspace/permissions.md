---
id: WSP-PERMISSIONS
title: Workspace Permissions
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - invariants.md
  - commands/README.md
  - ../identity/permissions.md
---

# Permissions

Workspace définit les capacités de ses intentions. Identity possède le
catalogue global, les affectations aux rôles et la décision d'autorisation.

## Capacités attribuables à un rôle

| Clé | Sensibilité | Intention |
|---|---|---|
| `workspace.profile.read` | Standard | Consulter le profil commercial. |
| `workspace.profile.update` | Elevated | Modifier le profil commercial. |
| `workspace.preferences.read` | Standard | Consulter les préférences. |
| `workspace.preferences.change` | Elevated | Remplacer les préférences. |
| `workspace.billing-identity.read` | Standard | Consulter l'identité de facturation. |
| `workspace.billing-identity.update` | Critical | Remplacer l'identité de facturation. |
| `workspace.lifecycle.close` | Critical | Fermer logiquement le Workspace. |

Les mutations impliquent la lecture correspondante :

```text
workspace.profile.update implies workspace.profile.read
workspace.preferences.change implies workspace.preferences.read
workspace.billing-identity.update implies workspace.billing-identity.read
```

Le rôle système owner doit conserver toutes les capacités Workspace actives de
ce catalogue. Les autres rôles sont configurés dans Identity selon la politique
produit.

---

## Capacités SystemActorOnly

| Clé | Usage borné |
|---|---|
| `workspace.lifecycle.activate` | terminer un bootstrap validé |
| `workspace.lifecycle.restrict` | appliquer une décision de sécurité ou conformité |
| `workspace.lifecycle.restore` | lever une restriction après remédiation |
| `workspace.lifecycle.close-for-compliance` | fermeture imposée par une autorité de confiance |

Ces clés ne peuvent pas être accordées à un rôle de Workspace.

---

## Règles d'évaluation

Une commande attribuable à un rôle exige :

1. un principal Identity actif ;
2. le même `WorkspaceId` dans la commande et la décision ;
3. un Workspace `Active` ;
4. la permission exacte ;
5. le niveau d'authentification requis ;
6. les préconditions métier de la commande.

`workspace.lifecycle.close` exige une élévation récente et une confirmation
explicite. La permission seule n'est jamais suffisante.

Une capacité `SystemActorOnly` exige une identité de workload ou un workflow de
confiance, une portée bornée, une corrélation et un audit. Une session humaine ne
peut pas se déclarer acteur système.

---

## Refus par défaut

Toute décision absente, expirée, rattachée à un autre Workspace ou calculée sur
une version de gouvernance obsolète est refusée.

Une restriction invalide immédiatement les décisions ordinaires en cache.
