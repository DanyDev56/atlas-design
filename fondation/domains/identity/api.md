---
id: IDN-PUBLIC-CONTRACT
title: Identity Public Contract
status: In Review
owner: Product
version: 1.0.1
last_updated: 2026-08-05

references:
  - scope.md
  - events.md
  - permissions.md
  - workflows.md
  - ../workspace/api.md
---

# Contrat public d'Identity

Ce document définit les capacités publiques d'Identity. Il ne prescrit ni un
transport HTTP, ni une technologie de stockage.

Les adaptateurs REST, messages ou appels internes doivent conserver les mêmes
intentions, erreurs et garanties.

---

## Principes

- les commandes publiques portent une intention et une clé d'idempotence ;
- les lectures ne permettent aucune modification ;
- les secrets utilisent des canaux spécialisés et ne figurent pas dans les
  réponses ordinaires ;
- une absence d'autorisation est refusée par défaut ;
- les erreurs exposées ne facilitent pas l'énumération des comptes ;
- les contrats sont versionnés et compatibles pendant leur période de support.

---

## Authentication

### Inscription

```text
registerUser(email, displayName, idempotencyKey)
```

La réponse publique confirme la prise en charge sans exposer l'existence
préalable de l'adresse lorsque le contexte est sensible à l'énumération.

### Vérification d'adresse

```text
verifyUserEmail(verificationProof, idempotencyKey)
```

### Authentification

```text
authenticate(authenticationInput)
→ AuthenticationProofReference
```

La credential brute est remise uniquement à l'adaptateur d'authentification.

### Sessions

```text
createSession(authenticationProofReference, sessionPolicy, idempotencyKey)
refreshSession(refreshCredential, idempotencyKey)
elevateSession(sessionId, authenticationProofReference, scope, idempotencyKey)
terminateOwnSessionElevation(sessionId, reason, idempotencyKey)
revokeSession(sessionId, idempotencyKey)
revokeAllOwnSessions(policy, idempotencyKey)
```

Les credentials retournées ne sont jamais persistées dans les événements.

### Récupération

```text
requestAccountRecovery(identifier)
completeAccountRecovery(recoveryProof, newCredential, idempotencyKey)
```

La demande retourne une réponse opaque. La complétion révoque les anciennes
sessions et credentials.

---

## User

```text
getCurrentUser()
updateCurrentUserProfile(changes, idempotencyKey)
changeCurrentUserEmail(newEmailProof, authenticationProof, idempotencyKey)
disableCurrentUser(authenticationProof, reason, idempotencyKey)
removeCurrentUser(authenticationProof, confirmation, idempotencyKey)
```

Une réactivation passe par le workflow de récupération ou de sécurité approprié.

---

## Membership et Invitation

Les commandes publiques correspondent aux intentions documentées :

- créer, suspendre, réactiver, retirer ou restaurer un membership ;
- changer ou transférer son rôle ;
- quitter son propre workspace ;
- créer, envoyer, relancer, accepter, refuser ou révoquer une invitation.

Chaque opération exige le `WorkspaceId`, la version attendue lorsque nécessaire,
la permission ou preuve requise et une clé d'idempotence.

---

## Role et Permission

Les commandes publiques permettent :

- de créer un rôle ;
- de modifier ses métadonnées ;
- de remplacer ses politiques d'attribution ou de transfert ;
- d'accorder ou retirer une permission ;
- de désactiver, réactiver ou archiver le rôle.

Les affectations directes de permission à un `User` ou `Membership` ne font pas
partie du contrat.

---

## Lectures publiques

### Principal courant

```text
resolvePrincipal(sessionCredential)
→
UserId
SessionId
SessionType
AuthenticationLevel
AuthenticatedAt
ElevationContext?
SecurityVersions
```

### Autorisation contextualisée

```text
authorize(userId, workspaceId, permissionKey, resourceContext?)
→
Allowed | Denied
DecisionId
EvaluatedVersions
DenialCategory?
RequiredStepUp?
```

Le contrat externe n'expose pas le détail sensible des politiques ayant conduit
à un refus. L'audit interne conserve l'explication complète.

### Membership courant

```text
getMembership(userId, workspaceId)
→
MembershipId
Status
RoleId
AuthorizationVersion
```

### Catalogue de permissions

Les autres domaines peuvent consulter les identifiants, clés, statuts et
versions publiques du catalogue. Ils ne peuvent pas modifier une affectation de
rôle sans passer par une commande Identity.

---

## Contrat minimal consommé depuis Workspace

Identity dépend de la lecture publique suivante :

```text
getWorkspaceAccessContext(workspaceId)
→
WorkspaceId
AccessState: Active | Restricted | Closed
GovernanceVersion
RequiresActiveOwner
```

Identity consomme également un fait versionné lorsque cet état change :

```text
WorkspaceAccessStateChanged
```

Ce contrat est confirmé par le bounded context Workspace dans son
[`api.md`](../workspace/api.md). Workspace reste seul propriétaire de son cycle
de vie.

---

## Contrat de gouvernance fourni à Workspace

Le bootstrap et la restauration d'un Workspace peuvent demander une preuve
minimale de readiness :

```text
getWorkspaceOwnerReadiness(workspaceId)
→
WorkspaceId
HasActiveOwner
IdentityGovernanceVersion
AssessedAt
ValidUntil
```

Cette lecture est réservée à un workflow de confiance borné au Workspace. Elle
ne révèle aucun `UserId`, `MembershipId`, `RoleId` ni détail de permission.

---

## Erreurs publiques

Les erreurs appartiennent à des catégories stables :

| Catégorie | Signification |
|---|---|
| `InvalidInput` | Entrée syntaxiquement invalide. |
| `Unauthenticated` | Preuve d'identité absente ou invalide. |
| `Unauthorized` | Autorité insuffisante. |
| `NotFound` | Ressource absente ou volontairement masquée. |
| `InvalidState` | Transition impossible depuis l'état courant. |
| `InvariantViolation` | Règle métier absolue menacée. |
| `Conflict` | Version, unicité ou concurrence incompatible. |
| `RateLimited` | Limite de sécurité ou d'usage atteinte. |
| `TemporarilyUnavailable` | Dépendance requise indisponible. |

Un adaptateur peut masquer une erreur plus précise lorsqu'elle révèlerait une
information sensible.

---

## Versioning

- les champs sont ajoutés de manière compatible ;
- un changement de sens exige une nouvelle version ;
- une clé de permission ou un nom d'événement n'est jamais réutilisé ;
- les consommateurs ignorent les champs additionnels inconnus ;
- les migrations cassantes suivent la politique de dépréciation d'Atlas.
