---
id: IDN-CMD-CHANGE-ROLE-TRANSFER-POLICY
title: ChangeRoleTransferPolicy
status: Draft
owner: Product
version: 1.0.0
last_updated: 2026-07-31

aggregate: Role

references:
  - README.md
  - ../aggregates.md
  - ../invariants.md
  - ../value-objects/RoleAssignmentPolicy.md
  - ../value-objects/RoleTransferPolicy.md
  - ../events/RoleTransferPolicyChanged.md
  - CreateRole.md
  - ChangeRoleAssignmentPolicy.md
  - TransferMembershipRole.md
---

# ChangeRoleTransferPolicy

## Objectif

`ChangeRoleTransferPolicy` remplace la politique de transfert actuelle d’un `Role` par une nouvelle `RoleTransferPolicy` valide.

```text
CurrentTransferPolicy
↓
NewTransferPolicy
```

La commande définit les conditions des futurs transferts.

Elle ne réalise aucun transfert.

---

## Agrégat

```text
Role
```

---

## Acteur

La commande peut être initiée par :

- un `Owner` ;
- un administrateur de rôles ;
- un administrateur de sécurité ;
- un workflow de gouvernance ;
- un `SystemActor` ;
- une source externe autoritaire ;
- un moteur de modèles ;
- un processus de récupération administrative.

---

## Permission

Permission recommandée :

```text
workspace.roles.change-transfer-policy
```

Permissions renforcées possibles :

```text
workspace.roles.change-privileged-transfer-policy
workspace.owners.change-transfer-policy
workspace.security.change-role-policy
```

---

## Données d’entrée

| Donnée | Type | Obligatoire |
|---|---|---:|
| `RoleId` | `RoleId` | Oui |
| `NewPolicy` | `RoleTransferPolicy` | Oui |
| `ChangedBy` | `UserId` ou `SystemActor` | Oui |
| `ChangedAt` | Instant | Oui |
| `ChangeReason` | `RoleTransferPolicyChangeReason` | Oui |
| `ChangeSource` | `RoleTransferPolicyChangeSource` | Oui |
| `ChangeRequestId` | Identifiant | Oui |
| `ExpectedRoleVersion` | Version | Recommandé |
| `ApprovalId` | Identifiant | Conditionnel |
| `SecurityReviewId` | Identifiant | Conditionnel |
| `ExternalReference` | Identifiant | Conditionnel |
| `ExternalVersion` | Version | Conditionnel |
| `TemplateId` | Identifiant | Conditionnel |
| `TemplateVersion` | Version | Conditionnel |
| `CorrelationId` | Identifiant | Non |

---

## Préconditions

- le rôle existe ;
- le rôle n’est ni archivé ni supprimé ;
- l’acteur ou le workflow est autorisé ;
- la source contrôle la politique ;
- la nouvelle politique est valide ;
- elle diffère de la politique actuelle ;
- elle est compatible avec `RoleAssignmentPolicy` ;
- les permissions référencées existent ;
- les protections des rôles système restent satisfaites ;
- une voie de récupération existe pour les rôles critiques ;
- les opérations de transfert en attente ont été évaluées ;
- les approbations requises sont valides ;
- la version attendue correspond ;
- la demande est idempotente.

---

## Compatibilité avec RoleAssignmentPolicy

Lorsque :

```text
NewPolicy.Transferability = Transferable
```

la politique d’attribution doit autoriser :

```text
RoleTransfer
```

Condition :

```text
Role.AssignmentPolicy.AllowedSources
contains RoleTransfer
```

Les types de cible autorisés par les deux politiques doivent également être compatibles.

---

## Classification du changement

Exemples :

```text
TransferEnabled
TransferDisabled
InitiatorRestriction
InitiatorExpansion
ApprovalRequirementAdded
ApprovalRequirementRemoved
SourceConfirmationAdded
SourceConfirmationRemoved
TargetAcceptanceAdded
TargetAcceptanceRemoved
AuthenticationHardening
AuthenticationRelaxation
RecoveryRestriction
RecoveryExpansion
```

Une réduction de sécurité peut nécessiter une approbation ou une revue.

---

## Opérations en attente

Le système doit analyser :

- les demandes de transfert ;
- les approbations ;
- les confirmations source ;
- les acceptations cible ;
- les transferts planifiés.

Une preuve émise sous une ancienne version peut devenir invalide.

Politique recommandée :

```text
pending transfer must match current TransferPolicyVersion
```

---

## Traitement métier

```text
1. Load Role
2. Verify idempotency
3. Verify Role state
4. Authorize actor or SystemActor
5. Verify policy control source
6. Validate NewTransferPolicy
7. Validate compatibility with AssignmentPolicy
8. Compare current and new policies
9. Classify security impact
10. Validate approvals and reviews
11. Analyze pending transfer workflows
12. Verify critical Role invariants
13. Verify administrative recovery path
14. Verify ExpectedRoleVersion
15. Replace TransferPolicy
16. Increment Role.Version
17. Record idempotency
18. Emit RoleTransferPolicyChanged
19. Commit atomically
```

---

## Effet sur les transferts en attente

La commande ne réalise ni n’annule automatiquement un transfert.

Elle peut rendre une demande :

```text
StillValid
RequiresReapproval
RequiresReconfirmation
RequiresReacceptance
Invalidated
```

Un événement secondaire peut signaler cette évolution.

---

## Rôle Owner

Pour un rôle owner, le produit peut imposer :

```text
Target must be HumanUser
SourceReplacementRole is required
NoTransientGapAllowed = true
MinimumActiveAssignmentsAfterTransfer >= 1
AdministrationRecoveryPolicy remains valid
```

Certaines exigences peuvent être non supprimables.

---

## Résultat

Après succès :

```text
Role
├── same RoleId
├── same WorkspaceId
├── same Metadata
├── same Status
├── same Permission assignments
├── same AssignmentPolicy
├── New TransferPolicy
└── incremented Version
```

---

## Événement produit

```text
RoleTransferPolicyChanged
```

Contenu recommandé :

- `RoleId`
- `WorkspaceId`
- `PreviousPolicy`
- `NewPolicy`
- `ChangeClassification`
- `PendingTransferImpactSummary`
- `ChangedBy`
- `ChangedAt`
- `ChangeReason`
- `ChangeSource`
- `ApprovalId`
- `SecurityReviewId`
- `ChangeRequestId`
- `CorrelationId`
- `RoleVersion`

---

## Événements secondaires possibles

```text
PendingRoleTransferInvalidated
PendingRoleTransferReapprovalRequired
PendingRoleTransferReconfirmationRequired
PendingRoleTransferReacceptanceRequired
```

---

## Idempotence

Clé :

```text
RoleId + ChangeRequestId
```

Empreinte :

```text
NewPolicy
ChangeReason
ChangeSource
ApprovalId
ExternalReference
ExternalVersion
TemplateId
TemplateVersion
```

Une répétition exacte retourne le résultat initial.

---

## Concurrence

La commande doit se protéger contre :

- un transfert en cours ;
- une autre modification de politique ;
- une modification de `RoleAssignmentPolicy` ;
- une désactivation ;
- un archivage ;
- une modification des permissions référencées.

`TransferMembershipRole` doit vérifier :

```text
ExpectedTransferPolicyVersion
ExpectedAssignmentPolicyVersion
```

avant commit.

---

## Atomicité

Le même commit contient :

```text
Role TransferPolicy update
Role Version update
Pending-transfer impact reference
Idempotency record
RoleTransferPolicyChanged
```

---

## Erreurs métier principales

```text
RoleNotFound
RoleArchived
ActorNotAuthorized
TransferPolicySourceNotAuthoritative
InvalidRoleTransferPolicy
RoleTransferPolicyAlreadyMatches
TransferPolicyNotCompatibleWithAssignmentPolicy
RequiredPermissionNotFound
SecurityReviewRequired
ApprovalRequired
ApprovalInvalid
OwnerRecoveryPathRequired
PendingTransferImpactNotHandled
RoleVersionConflict
IdempotencyConflict
```

Les erreurs détaillées de structure sont documentées dans :

```text
value-objects/RoleTransferPolicy.md
```

---

## Invariants

La commande préserve notamment :

```text
Role remains in same Workspace
RoleId remains unchanged
Permissions remain unchanged
Membership assignments remain unchanged
AssignmentPolicy remains unchanged
TransferPolicy remains compatible with AssignmentPolicy
Owner continuity remains recoverable
```

---

## Décisions de conception

- `TransferMode` est supprimé ;
- la commande reçoit une politique complète ;
- la politique détaillée appartient au `Value Object` ;
- aucun transfert existant n’est exécuté ou compensé silencieusement ;
- les preuves en attente sont liées à une version de politique ;
- l’autorisation d’un transfert exige la compatibilité des politiques d’attribution et de transfert.

---

## Synthèse

`ChangeRoleTransferPolicy` remplace atomiquement les règles applicables aux futurs transferts d’un rôle.

Elle modifie les conditions du transfert, jamais les memberships participants.