---
id: IDN-PERMISSIONS
title: Identity Permissions
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-05

references:
  - entities.md
  - relationships.md
  - invariants.md
  - commands/README.md
---

# Permissions

Ce document définit le catalogue canonique des permissions possédées par
Identity 1.0 et les règles d'évaluation des autorisations.

---

## Principe fondamental

Une permission est une capacité globale reconnue par Atlas.

Dans un `Workspace`, elle devient effective uniquement par la chaîne :

```text
Active User
  ↓
Active Membership in Active Workspace
  ↓
Active Role in the same Workspace
  ↓
Role Permission assignments
  ↓
Effective Permission set
```

Une permission n'est jamais attribuée directement à un `User` ou à un
`Membership`.

---

## Structure d'une Permission

| Propriété | Description |
|---|---|
| `PermissionId` | Identité stable. |
| `PermissionKey` | Clé canonique stable. |
| `OwningDomain` | Domaine qui définit la capacité. |
| `AssignmentScope` | `WorkspaceRole` ou `SystemActorOnly`. |
| `Sensitivity` | `Standard`, `Elevated` ou `Critical`. |
| `RequiredPermissions` | Prérequis explicites. |
| `ImpliedPermissions` | Permissions rendues effectives par implication. |
| `RequiredGrantPermission` | Autorité requise pour l'accorder. |
| `RequiredRevokePermission` | Autorité requise pour la retirer. |
| `Status` | `Active` ou `Deprecated`. |

Une clé dépréciée n'est jamais réutilisée avec un autre sens.

---

## Catalogue Workspace Membership

| Clé | Sensibilité | Intention couverte |
|---|---|---|
| `workspace.members.read` | Standard | Consulter les membres et leurs rôles. |
| `workspace.members.create` | Elevated | Créer directement un membership. |
| `workspace.members.invite` | Elevated | Créer, envoyer, relancer ou révoquer une invitation. |
| `workspace.members.change-role` | Elevated | Changer le rôle courant d'un membre. |
| `workspace.members.transfer-role` | Critical | Transférer atomiquement un rôle entre deux membres. |
| `workspace.members.suspend` | Elevated | Suspendre temporairement un membership. |
| `workspace.members.reactivate` | Elevated | Réactiver un membership suspendu. |
| `workspace.members.remove` | Elevated | Retirer administrativement un membership. |
| `workspace.members.restore` | Elevated | Restaurer un membership retiré. |

`LeaveWorkspace` est une capacité intrinsèque du membre actif, pas une
permission administrable de son rôle. Les protections du dernier owner restent
applicables.

---

## Catalogue Workspace Role

| Clé | Sensibilité | Intention couverte |
|---|---|---|
| `workspace.roles.read` | Standard | Consulter les rôles et permissions. |
| `workspace.roles.create` | Elevated | Créer un rôle autorisé. |
| `workspace.roles.update-metadata` | Elevated | Modifier les métadonnées d'un rôle. |
| `workspace.roles.change-assignment-policy` | Critical | Remplacer la politique d'attribution. |
| `workspace.roles.change-transfer-policy` | Critical | Remplacer la politique de transfert. |
| `workspace.roles.grant-permission` | Critical | Ajouter une permission à un rôle. |
| `workspace.roles.revoke-permission` | Critical | Retirer une permission d'un rôle. |
| `workspace.roles.disable` | Critical | Désactiver temporairement un rôle. |
| `workspace.roles.enable` | Critical | Réactiver un rôle. |
| `workspace.roles.archive` | Critical | Archiver définitivement un rôle. |

Une permission générique `workspace.roles.manage` n'appartient pas au catalogue
1.0. Les intentions sensibles restent séparées afin de respecter le moindre
privilège.

---

## Implications canoniques

Les permissions de mutation Membership impliquent `workspace.members.read`.
Les permissions de mutation Role impliquent `workspace.roles.read`.

```text
workspace.members.{create,invite,change-role,transfer-role,suspend,reactivate,remove,restore}
  implies workspace.members.read

workspace.roles.{create,update-metadata,change-assignment-policy,change-transfer-policy,
                 grant-permission,revoke-permission,disable,enable,archive}
  implies workspace.roles.read
```

Identity 1.0 ne déclare aucune dépendance obligatoire supplémentaire entre ces
clés. Les implications sont calculées à l'évaluation ; elles ne créent pas
d'affectations explicites dans le `Role`.

---

## Rôle owner

Le `Role` dont `RoleSystemType = Owner` conserve obligatoirement toutes les
permissions Identity de workspace actives listées dans les deux catalogues
ci-dessus. Elles forment son socle de gouvernance et ne peuvent pas être retirées
individuellement.

La création et les migrations de workspace réconcilient ce socle. Les autres
domaines peuvent ajouter leurs propres permissions owner obligatoires au moyen
de leur politique produit, sans en transférer la propriété à Identity.

---

## Capacités SystemActorOnly

Les capacités suivantes ne peuvent pas être accordées à un `Role` de workspace :

| Clé | Usage |
|---|---|
| `identity.invitations.expire` | Exécuter l'expiration temporelle. |
| `identity.sessions.create-for-user` | Création administrative exceptionnelle. |
| `identity.sessions.impersonate` | Session d'impersonation strictement auditée. |
| `identity.sessions.revoke` | Révocation d'une session appartenant à un autre sujet. |
| `identity.sessions.revoke-all` | Révocation globale pour un autre sujet. |

L'autorisation de ces capacités provient d'une politique de plateforme ou d'un
workflow de sécurité de confiance. Les rôles globaux de plateforme restent hors
du périmètre 1.0.

---

## Autorisations intrinsèques

Certaines intentions reposent sur la propriété du sujet ou sur une preuve bornée,
pas sur une permission de workspace :

- accepter ou refuser sa propre invitation ;
- mettre à jour son profil ;
- changer sa propre adresse e-mail ;
- quitter son membership ;
- créer, rafraîchir ou élever sa propre session ;
- révoquer ses propres sessions ;
- demander la récupération de son compte ;
- retirer sa propre identité après readiness.

Chaque intention exige néanmoins la preuve et le niveau d'authentification
adaptés.

---

## Algorithme d'évaluation

Pour une permission de workspace :

1. authentifier l'acteur ;
2. vérifier `UserStatus = Active` ;
3. charger le contexte public du `Workspace` ;
4. vérifier que le workspace permet l'accès ;
5. charger l'unique `Membership` de l'acteur ;
6. vérifier `MembershipStatus = Active` ;
7. charger le `Role` courant dans le même workspace ;
8. vérifier que le rôle est actif ;
9. calculer les permissions explicites et impliquées ;
10. vérifier la clé demandée ;
11. appliquer les politiques contextuelles, hiérarchiques et de séparation des
    responsabilités ;
12. appliquer les exigences de réauthentification ou d'approbation ;
13. refuser par défaut si une information requise est absente.

La possession de la clé est nécessaire mais peut ne pas être suffisante pour
une opération sensible.

---

## Invalidation

Une décision mise en cache devient obsolète après toute modification de :

- `UserSecurityVersion` ;
- statut du `User` ;
- statut ou rôle du `Membership` ;
- statut, permissions ou politiques du `Role` ;
- disponibilité ou version de gouvernance du `Workspace`.

Les réductions de privilèges prennent effet immédiatement.

---

## Responsabilité des autres domaines

Chaque domaine définit les permissions correspondant à ses propres actions, par
exemple `billing.invoices.issue`.

Identity :

- enregistre leur identité stable dans le catalogue global ;
- les associe aux rôles ;
- résout leur efficacité.

Le domaine propriétaire reste responsable de l'application de ses règles métier
après l'autorisation.
