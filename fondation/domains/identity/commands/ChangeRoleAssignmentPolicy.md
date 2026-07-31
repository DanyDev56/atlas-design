---
id: IDN-CMD-CHANGE-ROLE-ASSIGNMENT-POLICY
title: ChangeRoleAssignmentPolicy
status: Draft
owner: Product
version: 2.0.0
last_updated: 2026-07-31

aggregate: Role

references:
  - README.md
  - ../aggregates.md
  - ../invariants.md
  - ../value-objects/RoleAssignmentPolicy.md
  - ../events/RoleAssignmentPolicyChanged.md
  - CreateRole.md
  - ChangeMembershipRole.md
  - TransferMembershipRole.md
---

# ChangeRoleAssignmentPolicy

## Objectif

`ChangeRoleAssignmentPolicy` remplace la politique d’attribution actuelle d’un `Role` par une nouvelle `RoleAssignmentPolicy` valide.

```text
CurrentAssignmentPolicy
↓
NewAssignmentPolicy
```

La commande modifie les règles d’attribution.

Elle n’attribue aucun rôle et ne modifie aucun `Membership`.

---

## Agrégat

```text
Role
```

---

## Acteur

La commande peut être initiée par :

- un acteur humain autorisé ;
- un `SystemActor` ;
- une source externe autoritaire ;
- un moteur de modèles ;
- un processus de migration ;
- un processus de récupération administrative.

---

## Permission

Permission recommandée :

```text
workspace.roles.change-assignment-policy
```

Permissions renforcées possibles :

```text
workspace.roles.change-privileged-assignment-policy
workspace.owners.change-assignment-policy
workspace.security.change-role-policy
```

---

## Données d’entrée

| Donnée | Type | Obligatoire |
|---|---|---:|
| `RoleId` | `RoleId` | Oui |
| `NewPolicy` | `RoleAssignmentPolicy` | Oui |
| `EnforcementMode` | `AssignmentPolicyEnforcementMode` | Oui |
| `ChangedBy` | `UserId` ou `SystemActor` | Oui |
| `ChangedAt` | Instant | Oui |
| `ChangeReason` | `RoleAssignmentPolicyChangeReason` | Oui |
| `ChangeSource` | `RoleAssignmentPolicyChangeSource` | Oui |
| `ChangeRequestId` | Identifiant | Oui |
| `ExpectedRoleVersion` | Version | Recommandé |
| `ApprovalId` | Identifiant | Conditionnel |
| `SecurityReviewId` | Identifiant | Conditionnel |
| `ComplianceReviewId` | Identifiant | Conditionnel |
| `RemediationPlanId` | Identifiant | Conditionnel |
| `ExternalReference` | Identifiant | Conditionnel |
| `ExternalVersion` | Version | Conditionnel |
| `TemplateId` | Identifiant | Conditionnel |
| `TemplateVersion` | Version | Conditionnel |
| `CorrelationId` | Identifiant | Non |

---

## EnforcementMode

Valeurs :

```text
FutureAssignmentsOnly
RequireCurrentCompliance
CreateRemediationPlan
```

### FutureAssignmentsOnly

La nouvelle politique s’applique aux affectations futures.

Les affectations existantes ne sont pas modifiées.

### RequireCurrentCompliance

La commande échoue si une affectation actuelle est non conforme.

### CreateRemediationPlan

La commande réussit uniquement si un plan valide couvre les non-conformités.

---

## Préconditions

- le rôle existe ;
- le `Workspace` existe ;
- le rôle n’est ni archivé ni supprimé ;
- l’acteur est autorisé ;
- la source contrôle cette politique ;
- la nouvelle politique est valide ;
- la nouvelle politique diffère de l’actuelle ;
- les permissions référencées existent ;
- les contraintes système restent respectées ;
- les affectations existantes ont été évaluées ;
- le mode d’application est satisfait ;
- les éventuelles approbations sont valides ;
- la version attendue correspond ;
- la demande est idempotente.

---

## Classification du changement

Le système compare les deux politiques et classe les différences.

Exemples :

```text
SecurityHardening
SecurityRelaxation
WorkflowRestriction
WorkflowExpansion
CapacityReduction
CapacityIncrease
IdentityRestriction
IdentityExpansion
ApprovalRequirementAdded
ApprovalRequirementRemoved
AuthenticationRequirementAdded
AuthenticationRequirementRemoved
```

Une réduction de sécurité peut imposer :

- une authentification renforcée ;
- une approbation ;
- une revue de sécurité ;
- une notification.

---

## Traitement métier

```text
1. Load Role
2. Verify idempotency
3. Verify Role state
4. Authorize actor or SystemActor
5. Verify source authority
6. Validate NewPolicy
7. Compare CurrentPolicy and NewPolicy
8. Classify security impact
9. Validate required approvals and reviews
10. Analyze existing assignments
11. Apply EnforcementMode
12. Verify critical Role invariants
13. Verify administrative recovery path
14. Verify ExpectedRoleVersion
15. Replace AssignmentPolicy
16. Increment Role.Version
17. Record idempotency
18. Emit RoleAssignmentPolicyChanged
19. Commit atomically
```

---

## Affectations existantes

La commande ne modifie aucun `Membership`.

Elle calcule un résultat de conformité :

```text
AssignmentComplianceSummary
├── CompliantCount
├── WarningCount
├── NonCompliantCount
├── RequiresRemediation
└── RemediationPlanId
```

Toute correction nécessite une autre commande ou un workflow explicite.

---

## Rôles système

Pour un rôle système, certaines protections peuvent être non modifiables.

Exemple pour `Owner` :

```text
RequiresHumanAssignee = true
MinimumActiveAssignments >= 1
AdministrationRecoveryPolicy remains valid
```

La commande doit appliquer les invariants produit en plus de la validation générique du `Value Object`.

---

## Concurrence avec une affectation

Une commande attribuant le rôle doit vérifier la version de politique utilisée.

```text
ExpectedAssignmentPolicyVersion
=
CurrentAssignmentPolicyVersion
```

Une affectation validée sous une ancienne politique ne doit pas être commitée après son remplacement sans réévaluation.

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
├── same TransferPolicy
├── New AssignmentPolicy
└── incremented Version
```

---

## Événement produit

```text
RoleAssignmentPolicyChanged
```

Contenu recommandé :

- `RoleId`
- `WorkspaceId`
- `PreviousPolicy`
- `NewPolicy`
- `ChangeClassification`
- `EnforcementMode`
- `AssignmentComplianceSummary`
- `RemediationPlanId`
- `ChangedBy`
- `ChangedAt`
- `ChangeReason`
- `ChangeSource`
- `ApprovalId`
- `SecurityReviewId`
- `ComplianceReviewId`
- `ChangeRequestId`
- `CorrelationId`
- `RoleVersion`

---

## Événements secondaires possibles

```text
RoleAssignmentsBecameNonCompliant
RoleAssignmentRemediationRequired
RoleAssignmentPolicyComplianceReviewRequested
```

Ils ne sont produits que lorsque leur condition métier est satisfaite.

---

## Idempotence

Clé recommandée :

```text
RoleId + ChangeRequestId
```

L’empreinte inclut :

```text
NewPolicy
EnforcementMode
ChangeReason
ChangeSource
ApprovalId
ExternalReference
ExternalVersion
TemplateId
TemplateVersion
```

La répétition exacte retourne le résultat initial.

Une réutilisation avec une autre intention produit :

```text
IdempotencyConflict
```

---

## Atomicité

Le même commit contient :

```text
Role AssignmentPolicy update
Role Version update
Compliance summary reference
Remediation reference
Idempotency record
RoleAssignmentPolicyChanged
```

---

## Erreurs métier principales

```text
RoleNotFound
RoleArchived
ActorNotAuthorized
AssignmentPolicySourceNotAuthoritative
InvalidRoleAssignmentPolicy
RoleAssignmentPolicyAlreadyMatches
RequiredPermissionNotFound
SecurityReviewRequired
ComplianceReviewRequired
ApprovalRequired
ApprovalInvalid
ExistingAssignmentsNotCompliant
RemediationPlanRequired
RemediationPlanInvalid
OwnerRecoveryPathRequired
RoleVersionConflict
IdempotencyConflict
```

Les erreurs détaillées de structure de politique sont définies dans :

```text
value-objects/RoleAssignmentPolicy.md
```

---

## Invariants

La commande préserve notamment :

```text
Role remains in same Workspace
RoleId remains unchanged
Permissions remain unchanged
Membership assignments remain unchanged
Assignment policy remains internally coherent
Owner continuity remains administrable
```

---

## Décisions de conception

- la commande reçoit une politique complète ;
- l’API peut fournir un patch, mais l’application construit la valeur complète avant d’appeler le domaine ;
- les règles détaillées appartiennent au `Value Object` ;
- les affectations existantes ne sont jamais corrigées silencieusement ;
- une réduction de sécurité est explicitement détectée ;
- la nouvelle version s’applique aux décisions futures.

---

## Synthèse

`ChangeRoleAssignmentPolicy` remplace atomiquement les règles d’attribution d’un rôle, après validation de leur cohérence, de leur sécurité et de leur impact sur les affectations existantes.

Elle modifie la règle, jamais les détenteurs.