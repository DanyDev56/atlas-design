---
id: IDN-CMD-ELEVATE-SESSION
title: ElevateSession
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

aggregate: Session

invariants:
  - IDN-INV-010
  - IDN-INV-012
  - IDN-INV-013
  - IDN-INV-014
  - IDN-INV-022

references:
  - README.md
  - ../entities.md
  - ../aggregates.md
  - ../relationships.md
  - ../value-objects.md
  - ../invariants.md
  - ../permissions.md
  - ../events.md
  - CreateSession.md
  - RefreshSession.md
  - RevokeSession.md
  - RevokeAllUserSessions.md
  - ExpireSessionElevation.md
  - TerminateSessionElevation.md
  - ExpireSession.md
  - ../workflows.md
  - ../decision-record.md
---

# ElevateSession

## 1. Objectif

La commande `ElevateSession` renforce temporairement le niveau d’assurance d’une `Session` active après validation d’une nouvelle `AuthenticationProof`.

Elle permet d’autoriser des opérations sensibles qui exigent une authentification :

- plus forte ;
- plus récente ;
- fondée sur des méthodes spécifiques ;
- liée à une intention précise ;
- limitée dans le temps.

    active standard Session
    +
    valid step-up AuthenticationProof
            ↓
    ElevateSession
            ↓
    temporarily elevated Session

La commande garantit que :

- la session existe ;
- la session est active ;
- le `User` existe toujours ;
- le `User` reste autorisé à utiliser la session ;
- une nouvelle preuve d’authentification valide est fournie ;
- la preuve appartient au même `User` que la session ;
- le niveau d’assurance obtenu satisfait la politique demandée ;
- les méthodes d’authentification requises ont été utilisées ;
- l’authentification est suffisamment récente ;
- l’élévation possède une durée courte et bornée ;
- l’élévation ne modifie pas l’identité de la session ;
- l’élévation ne modifie pas les permissions du `Role` ;
- l’élévation ne crée pas de permission directe ;
- les opérations sensibles restent soumises à l’autorisation du workspace ;
- l’élévation peut expirer indépendamment de la session principale ;
- l’élévation peut être révoquée ou invalidée ;
- l’opération est idempotente et auditable.

---

## 2. Intention métier

La commande répond à l’intention suivante :

    temporarily strengthen the authentication context
    of an existing Session
    for sensitive operations

Elle ne signifie pas :

    grant new business Permissions

Elle ne signifie pas non plus :

    create a new User identity

ou :

    permanently transform the Session

---

## 3. Step-up authentication

L’élévation repose sur une authentification renforcée :

    active Session
            ↓
    sensitive action requested
            ↓
    current assurance insufficient
            ↓
    step-up authentication required
            ↓
    AuthenticationProof produced
            ↓
    ElevateSession
            ↓
    sensitive action may be authorized

La step-up authentication vérifie de nouveau l’identité du `User` avec un niveau de confiance adapté au risque de l’opération.

---

## 4. Distinction avec CreateSession

`CreateSession` ouvre une nouvelle session :

    AuthenticationProof
            ↓
    CreateSession
            ↓
    new Session

`ElevateSession` renforce une session existante :

    existing active Session
    +
    stronger AuthenticationProof
            ↓
    ElevateSession
            ↓
    same Session with temporary elevation

Après une élévation :

    SessionId_before
    =
    SessionId_after

---

## 5. Distinction avec RefreshSession

`RefreshSession` renouvelle les credentials techniques d’une session :

    existing Session
    +
    RefreshCredential
            ↓
    renewed credentials

`ElevateSession` augmente temporairement l’assurance :

    existing Session
    +
    new AuthenticationProof
            ↓
    temporary elevated assurance

Un refresh credential ne constitue pas, à lui seul, une preuve suffisante pour une élévation.

---

## 6. Distinction avec Permission

Une élévation ne crée aucune permission.

La chaîne d’autorisation reste :

    Session
    → User
    → Membership
    → Role
    → Permission

L’autorisation d’une action sensible exige généralement :

    Session is valid
    AND
    Session elevation satisfies required assurance
    AND
    Membership is valid
    AND
    Role is active
    AND
    Permission is effective
    AND
    contextual policies are satisfied

Ainsi :

    elevated Session
    without required Permission
    =
    access denied

et :

    required Permission
    without sufficient Session assurance
    =
    step-up authentication required

---

## 7. Séparation entre authentification et autorisation

L’authentification répond à la question :

    who is currently controlling this Session
    and with which assurance?

L’autorisation répond à la question :

    may this authenticated User
    perform this action
    in this Workspace context?

`ElevateSession` traite uniquement la première question.

---

## 8. Agrégat concerné

    Session

La commande modifie un seul agrégat `Session`.

Elle consulte également :

- le `User` ;
- l’état de sécurité du `User` ;
- les versions de sécurité ;
- l’`AuthenticationProof` ;
- la politique d’élévation ;
- le contexte de risque ;
- le contexte de l’appareil ;
- le contexte du client ;
- l’éventuelle intention sensible ;
- les élévations existantes ;
- les restrictions d’impersonation ;
- les restrictions des sessions de récupération ;
- les restrictions des sessions fédérées.

Elle ne modifie pas directement :

- le `User` ;
- le `Workspace` ;
- le `Membership` ;
- le `Role` ;
- la `Permission` ;
- les credentials de connexion principaux ;
- les facteurs MFA du `User`.

---

## 9. État principal de la Session

La session conserve son cycle de vie principal :

    Active
    Revoked
    Expired

Seule une session :

    Active

peut être élevée.

Une session révoquée ou expirée ne peut pas être réactivée par `ElevateSession`.

---

## 10. État d’élévation

L’élévation peut être représentée par un état indépendant :

    SessionElevationStatus
    ├── Active
    ├── Expired
    └── Terminated

L'absence d'élévation est modélisée par l'absence de la valeur, pas par `None`.

La session principale peut rester :

    Session.Status = Active

alors que :

    Session.Elevation.Status = Expired

Dans ce cas, les actions ordinaires restent possibles, mais les actions exigeant une élévation sont refusées.

---

## 11. Modèle recommandé

Structure conceptuelle :

    Session
    ├── SessionId
    ├── UserId
    ├── Status
    ├── BaseAuthenticationContext
    ├── Elevation
    │   ├── Status
    │   ├── AssuranceLevel
    │   ├── AuthenticationMethods
    │   ├── ElevatedAt
    │   ├── ExpiresAt
    │   ├── AuthenticationProofId
    │   ├── ElevationScope
    │   ├── IntentionId
    │   ├── PolicyId
    │   ├── RiskAssessmentId
    │   └── ElevationVersion
    ├── UserSecurityVersion
    ├── AuthenticationStateVersion
    ├── SessionSecurityVersion
    └── Version

---

## 12. SessionElevation

`SessionElevation` peut être modélisée comme un `Value Object`.

Structure recommandée :

    SessionElevation
    ├── Status
    ├── AssuranceLevel
    ├── AuthenticationMethodSet
    ├── ElevatedAt
    ├── ExpiresAt
    ├── MaximumActionCount
    ├── RemainingActionCount
    ├── ElevationScope
    ├── IntentionId
    ├── AuthenticationProofId
    ├── ElevationPolicyId
    ├── RiskAssessmentId
    ├── DeviceAssessmentId
    ├── ActorUserId
    ├── SubjectUserId
    └── ElevationVersion

---

## 13. Élévation temporaire

Une élévation ne doit pas être permanente.

Elle possède obligatoirement :

    ElevatedAt
    ExpiresAt

avec :

    ExpiresAt > ElevatedAt

et :

    ExpiresAt - ElevatedAt
    <= ElevationPolicy.MaximumLifetime

---

## 14. Pourquoi l’élévation doit être courte

Une élévation donne accès à des opérations sensibles.

Une durée excessive augmente les risques liés :

- à l’abandon d’un poste ;
- au vol de session ;
- à une machine partagée ;
- à une compromission après authentification ;
- à l’utilisation involontaire d’une session privilégiée ;
- au contournement de l’intention initiale.

La politique recommandée est :

    short-lived elevation
    +
    explicit sensitive intention
    +
    automatic expiration

---

## 15. AssuranceLevel

Valeurs possibles :

    Low
    Standard
    High
    VeryHigh

ou, si une nomenclature normative est retenue :

    AAL1
    AAL2
    AAL3

Le projet doit utiliser une seule nomenclature cohérente.

---

## 16. Assurance de base et assurance élevée

La session possède un niveau de base :

    Session.BaseAuthenticationContext.AssuranceLevel

L’élévation ajoute un niveau temporaire :

    Session.Elevation.AssuranceLevel

Le niveau effectif est :

    if Elevation is Active
    then max(
      BaseAssuranceLevel,
      ElevationAssuranceLevel
    )
    else BaseAssuranceLevel

---

## 17. Pas de diminution par ElevateSession

`ElevateSession` ne doit jamais réduire le niveau d’assurance.

Condition :

    RequestedElevationAssurance
    >
    CurrentEffectiveAssurance

Si la preuve produit un niveau égal ou inférieur, l’élévation est inutile.

Erreur recommandée :

    ElevationDoesNotIncreaseAssurance

---

## 18. Méthodes d’authentification

Une élévation peut exiger une ou plusieurs méthodes :

    Password
    Passkey
    SecurityKey
    AuthenticatorApp
    OneTimePassword
    RecoveryCode
    ClientCertificate
    IdentityProvider
    BiometricDeviceAssertion
    AdministrativeRecovery

La politique peut exiger :

    specific method

ou :

    method combination

Exemple :

    Password
    +
    AuthenticatorApp

ou :

    Passkey with user verification

---

## 19. Méthodes résistantes au phishing

Certaines opérations critiques peuvent exiger une méthode résistante au phishing.

Exemples conceptuels :

    SecurityKey
    Passkey with verified origin
    ClientCertificate

Une authentification par SMS ou code email peut être jugée insuffisante pour certaines opérations.

---

## 20. AuthenticationProof

La commande reçoit une nouvelle :

    AuthenticationProof

Cette preuve doit avoir été produite après une authentification step-up réussie.

Structure recommandée :

    AuthenticationProof
    ├── AuthenticationProofId
    ├── UserId
    ├── AuthenticationMethodSet
    ├── AuthenticationAssuranceLevel
    ├── AuthenticatedAt
    ├── ValidUntil
    ├── AuthenticationFlowId
    ├── SessionId
    ├── IntentionId
    ├── RequiredAction
    ├── IdentityProvider
    ├── RiskAssessmentId
    ├── DeviceAssessmentId
    ├── UserSecurityVersion
    ├── AuthenticationStateVersion
    ├── SingleUse
    └── ProofIntegrity

---

## 21. Liaison de la preuve à la Session

La preuve d’élévation doit idéalement être liée à :

    SessionId

Condition :

    AuthenticationProof.SessionId
    =
    Session.SessionId

Cette règle empêche l’utilisation d’une preuve produite pour une autre session.

---

## 22. Liaison de la preuve à l’intention

La preuve peut être liée à une intention sensible :

    IntentionId

Exemple :

    transfer Workspace ownership
    approve payment
    change authentication factors
    export sensitive data
    impersonate another User
    revoke all Sessions

Condition recommandée :

    AuthenticationProof.IntentionId
    =
    ElevateSession.IntentionId

---

## 23. Authentication freshness

L’authentification doit être suffisamment récente.

Condition :

    ElevatedAt - AuthenticationProof.AuthenticatedAt
    <= ElevationPolicy.MaximumAuthenticationAge

Exemple :

    MaximumAuthenticationAge = 2 minutes

Une preuve plus ancienne doit être refusée.

---

## 24. Preuve à usage unique

La preuve d’élévation devrait généralement être :

    SingleUse = true

Après une élévation réussie :

    AuthenticationProof
    → Consumed

Elle ne doit pas pouvoir servir à élever plusieurs sessions ou plusieurs fois la même session, sauf politique explicite.

---

## 25. ElevationScope

L’élévation ne doit pas nécessairement autoriser toutes les actions sensibles.

Valeurs possibles :

    SessionWide
    PermissionScoped
    ActionScoped
    ResourceScoped
    WorkflowScoped
    SingleUse

---

## 26. SessionWide

L’élévation est utilisable pour toutes les actions dont les exigences sont compatibles.

Cette portée est simple, mais plus large.

Elle doit avoir une durée très courte.

---

## 27. PermissionScoped

L’élévation est limitée à certaines permissions.

Exemple :

    workspace.billing.payment.approve
    workspace.members.transfer-role

L’élévation ne crée pas ces permissions.

Elle indique seulement que la session satisfait l’assurance exigée pour leur exercice.

---

## 28. ActionScoped

L’élévation couvre une action précise.

Exemple :

    approve Invoice 123

Structure possible :

    ActionName
    ResourceType
    ResourceId
    WorkspaceId

---

## 29. ResourceScoped

L’élévation couvre un ensemble restreint de ressources.

Exemple :

    export data
    for Workspace X
    during current administrative workflow

---

## 30. WorkflowScoped

L’élévation reste valide uniquement pour un workflow identifié :

    WorkflowId
    CorrelationId
    IntentionId

Lorsque le workflow se termine, l’élévation peut être invalidée.

---

## 31. SingleUse

L’élévation ne peut autoriser qu’une seule action sensible.

Après consommation :

    RemainingActionCount = 0
    Elevation.Status = Terminated
    TerminationReason = ScopeConsumed

Cette stratégie est recommandée pour certaines opérations critiques.

---

## 32. Recommandation de portée

Politique recommandée :

    standard sensitive actions
    → short SessionWide elevation

    high-risk operations
    → ActionScoped or WorkflowScoped elevation

    critical irreversible operations
    → SingleUse elevation

---

## 33. ElevationPolicy

La commande est gouvernée par :

    SessionElevationPolicy

Structure recommandée :

    SessionElevationPolicy
    ├── PolicyId
    ├── MinimumAssuranceLevel
    ├── RequiredAuthenticationMethods
    ├── RequiredMethodProperties
    ├── MaximumAuthenticationAge
    ├── MaximumElevationLifetime
    ├── AllowedSessionTypes
    ├── AllowedElevationScopes
    ├── MaximumActionCount
    ├── DeviceBindingRequired
    ├── ClientBindingRequired
    ├── RiskReassessmentRequired
    ├── MaximumRiskLevel
    ├── PhishingResistanceRequired
    ├── UserVerificationRequired
    ├── SingleUseProofRequired
    ├── ImpersonationAllowed
    ├── FederationAllowed
    └── NotificationPolicy

---

## 34. Politique liée à l’action

La politique peut être déterminée à partir de l’action sensible demandée.

Exemple :

    SensitiveActionPolicy
    ├── Action
    ├── RequiredPermission
    ├── MinimumAssuranceLevel
    ├── MaximumAuthenticationAge
    ├── RequiredAuthenticationMethods
    ├── ElevationScope
    ├── ElevationLifetime
    ├── SingleUse
    └── ApprovalRequirements

`ElevateSession` valide l’assurance.

L’autorisation métier de l’action reste distincte.

---

## 35. Données d’entrée

### 35.1 Données obligatoires

| Donnée | Type | Description |
|---|---|---|
| `SessionId` | `SessionId` | Session à élever. |
| `AuthenticationProofId` | Identifiant | Preuve step-up validée. |
| `ElevationPolicyId` | Identifiant | Politique d’élévation applicable. |
| `ElevationScope` | `ElevationScope` | Portée demandée. |
| `ElevatedAt` | Instant | Date métier de l’élévation. |
| `ElevateSessionRequestId` | Identifiant | Identifiant idempotent. |

### 35.2 Données facultatives ou conditionnelles

| Donnée | Type | Description |
|---|---|---|
| `IntentionId` | Identifiant | Intention sensible associée. |
| `WorkspaceId` | `WorkspaceId` | Contexte éventuel de l’intention. |
| `RequiredAction` | Identifiant | Action sensible demandée. |
| `RequiredPermissionId` | `PermissionId` | Permission concernée à titre contextuel. |
| `ResourceType` | Identifiant | Type de ressource ciblée. |
| `ResourceId` | Identifiant | Ressource ciblée. |
| `MaximumActionCount` | Entier | Nombre maximal d’utilisations. |
| `RequestedLifetime` | Durée | Durée préférée, bornée par la politique. |
| `DeviceId` | `DeviceId` | Appareil attendu. |
| `ClientApplicationId` | Identifiant | Application cliente. |
| `RiskAssessmentId` | Identifiant | Évaluation de risque. |
| `DeviceAssessmentId` | Identifiant | Évaluation de l’appareil. |
| `ExpectedSessionVersion` | Version | Version attendue de la session. |
| `ExpectedSessionSecurityVersion` | Version | Version de sécurité attendue. |
| `ExpectedUserSecurityVersion` | Version | Version du `User` attendue. |
| `CorrelationId` | Identifiant | Corrélation avec un workflow. |
| `Metadata` | Métadonnées contrôlées | Données techniques limitées. |

---

## 36. Données calculées

La commande calcule :

    EffectiveAssuranceLevel
    ElevationExpiresAt
    MaximumActionCount
    RemainingActionCount
    ElevationVersion
    SessionSecurityVersion
    SessionVersion

Le client ne doit pas imposer directement :

    ElevationExpiresAt

au-delà des limites définies par la politique.

---

## 37. Préconditions

Avant exécution :

- la session existe ;
- la session est active ;
- la session n’est pas expirée ;
- la session n’est pas révoquée ;
- le `User` existe ;
- le statut du `User` autorise l’élévation ;
- les versions de sécurité correspondent ;
- l’`AuthenticationProof` existe ;
- la preuve appartient au même `User` ;
- la preuve est liée à la session lorsque requis ;
- la preuve est liée à l’intention lorsque requis ;
- la preuve est intègre ;
- la preuve n’est pas expirée ;
- la preuve n’a pas déjà été consommée ;
- la preuve provient d’une source autorisée ;
- le niveau d’assurance satisfait la politique ;
- les méthodes requises sont présentes ;
- les propriétés des méthodes sont satisfaites ;
- la preuve est suffisamment récente ;
- le type de session autorise l’élévation ;
- la portée demandée est autorisée ;
- le risque est acceptable ;
- l’appareil est acceptable ;
- le client est acceptable ;
- la durée calculée reste bornée ;
- une élévation existante n’entre pas en conflit ;
- la demande est idempotente ;
- aucune modification concurrente incompatible n’a gagné.

---

## 38. États du User

Règles Identity 1.0 :

    Active
    → elevation allowed

    PendingVerification
    → only for restricted activation workflow

    Disabled
    → elevation forbidden

    Removed
    → elevation forbidden

    AuthenticationLockStatus = TemporarilyLocked
    → elevation forbidden

Une élévation ne doit jamais contourner l’état de sécurité du compte.

---

## 39. Types de Session

Valeurs possibles :

    Interactive
    Remembered
    Privileged
    Recovery
    Impersonation
    Service
    Federated
    Temporary

Toutes ne doivent pas être élevables de la même manière.

---

## 40. Interactive Session

Une session interactive ordinaire peut être élevée après authentification renforcée.

C’est le cas principal de la commande.

---

## 41. Remembered Session

Une session persistante peut être élevée, mais :

- l’authentification initiale mémorisée ne suffit pas ;
- une nouvelle preuve interactive est requise ;
- l’élévation doit être courte ;
- l’élévation ne prolonge pas la durée absolue de la session ;
- l’élévation doit respecter le binding d’appareil.

---

## 42. Privileged Session

Une session déjà `Privileged` peut encore recevoir une élévation plus forte ou plus ciblée si la politique le prévoit.

Exemple :

    High assurance Session
            ↓
    VeryHigh single-use elevation

Une élévation qui n’augmente ni l’assurance ni la portée utile doit être refusée ou traitée comme idempotente selon le contexte.

---

## 43. Recovery Session

Une session de récupération ne doit pas être transformée en session métier privilégiée.

Elle peut uniquement être élevée dans les limites du workflow de récupération.

Exemple :

    Recovery Session
    → reset authentication factors

mais pas :

    Recovery Session
    → approve payment

---

## 44. Impersonation Session

L’élévation d’une session d’impersonation est particulièrement sensible.

Le système doit conserver :

    ActorUserId
    SubjectUserId

La preuve d’authentification renforcée doit normalement appartenir à :

    ActorUserId

et non au sujet impersonné.

La politique doit explicitement autoriser l’action pendant une impersonation.

---

## 45. Service Session

Une session technique ne devrait pas utiliser une step-up humaine ordinaire.

Les opérations sensibles des comptes de service doivent reposer sur :

- des credentials adaptés ;
- des certificats ;
- une attestation ;
- une identité de workload ;
- une approbation humaine externe ;
- une orchestration distincte.

Politique recommandée :

    SessionType = Service
    → ElevateSession forbidden

sauf décision architecturale explicite.

---

## 46. Federated Session

Une session fédérée peut être élevée :

- auprès du fournisseur d’identité ;
- localement avec un facteur complémentaire ;
- par une combinaison des deux.

Le système doit vérifier que le contexte d’assurance externe est digne de confiance et suffisamment récent.

---

## 47. Temporary Session

Une session temporaire ne peut être élevée que dans son workflow autorisé.

L’élévation ne doit pas élargir sa finalité.

---

## 48. Élévation existante

La session peut déjà posséder une élévation active.

Plusieurs politiques sont possibles :

    Replace
    Extend
    Merge
    Reject
    KeepStronger

---

## 49. Replace

La nouvelle élévation remplace l’ancienne.

Approche simple, mais pouvant réduire involontairement une portée existante.

---

## 50. Extend

La nouvelle élévation prolonge la durée de l’élévation existante.

Cette stratégie peut créer une élévation quasi permanente.

Elle est déconseillée sans limites strictes.

---

## 51. Merge

Les portées sont fusionnées.

Cette stratégie augmente progressivement les privilèges d’authentification et complique l’audit.

Elle est déconseillée dans un premier modèle.

---

## 52. Reject

Toute élévation active bloque une nouvelle élévation.

Cette stratégie est prévisible, mais peut gêner certains workflows.

---

## 53. KeepStronger

La nouvelle élévation remplace l’ancienne uniquement si elle est plus forte ou plus spécifique.

---

## 54. Recommandation

Politique initiale recommandée :

    one active elevation per Session

avec :

    new valid elevation
    → replaces existing elevation

uniquement si :

    new assurance >= current assurance

et :

    scope replacement is explicitly accepted

La précédente élévation est alors terminée de manière auditable.

---

## 55. Pas d’extension silencieuse

Une nouvelle élévation ne doit pas simplement repousser :

    ExpiresAt

sans nouvelle preuve.

Chaque extension de durée doit reposer sur une nouvelle authentification step-up.

---

## 56. Durée maximale

La durée est calculée comme :

    ElevationExpiresAt
    =
    min(
      ElevatedAt + Policy.MaximumElevationLifetime,
      Session.ExpiresAt,
      AuthenticationProof.ValidUntil when applicable
    )

L’élévation ne peut jamais survivre à la session principale.

---

## 57. Expiration par inactivité

Une élévation peut posséder une expiration par inactivité :

    ElevationIdleExpiresAt

Exemple :

    elevated for 10 minutes
    but expires after 2 minutes without sensitive activity

Cette complexité doit être introduite uniquement si nécessaire.

---

## 58. Action count

Une élévation peut limiter le nombre d’utilisations :

    MaximumActionCount
    RemainingActionCount

Pour une élévation `SingleUse` :

    MaximumActionCount = 1
    RemainingActionCount = 1

Après consommation :

    RemainingActionCount = 0

---

## 59. Consommation de l’élévation

`ElevateSession` crée l’élévation.

La consommation par une action sensible peut être gérée par :

    ConsumeSessionElevation

ou intégrée atomiquement à la commande métier sensible.

Recommandation :

    critical action command
    validates and consumes elevation
    in the same logical transaction

afin d’éviter un double usage concurrent.

---

## 60. Validation de l’élévation lors d’une action

Une action sensible doit vérifier :

    Session.Status = Active
    AND
    Elevation.Status = Active
    AND
    CurrentTime < Elevation.ExpiresAt
    AND
    Elevation.AssuranceLevel >= RequiredAssuranceLevel
    AND
    RequiredAction is covered by Elevation.Scope
    AND
    Required Workspace is covered
    AND
    Required Resource is covered
    AND
    RemainingActionCount > 0 when applicable
    AND
    Session security versions remain current

---

## 61. Autorisation toujours requise

Même après élévation :

    Actor must still have required Permission

Exemple :

    Session elevated to VeryHigh
    but Role lacks workspace.members.transfer-role
            ↓
    transfer denied

---

## 62. Liaison au Workspace

Une élévation peut être globale ou liée à un workspace.

Pour une opération sensible de workspace, la liaison recommandée est :

    Elevation.WorkspaceId
    =
    TargetWorkspaceId

Cela évite d’utiliser une preuve obtenue dans un contexte pour une action sensible dans un autre workspace.

---

## 63. Liaison à la Permission

La propriété :

    RequiredPermissionId

peut être conservée dans l’intention ou la portée.

Elle est informative pour l’élévation.

La vérification réelle de la permission reste effectuée par le moteur d’autorisation.

---

## 64. Liaison à la ressource

Pour une action irréversible :

    ResourceType
    ResourceId

peuvent être liés à l’élévation.

Exemple :

    delete Client 123
    approve Invoice 456
    transfer Workspace X

---

## 65. RiskAssessment

Une nouvelle évaluation de risque peut être requise lors de l’élévation.

Structure possible :

    ElevationRiskAssessment
    ├── RiskLevel
    ├── DeviceTrust
    ├── NetworkContext
    ├── GeographicContext
    ├── SessionAge
    ├── AuthenticationMethodRisk
    ├── BehavioralSignals
    └── Decision

Valeurs :

    Low
    Medium
    High
    Critical

---

## 66. Politique de risque

Exemple :

    Low
    → elevation allowed

    Medium
    → elevation allowed with stronger method

    High
    → elevation denied or additional verification required

    Critical
    → elevation denied and Session review requested

---

## 67. Device binding

L’élévation peut être liée au même appareil que la session.

Condition :

    AuthenticationProof.DeviceId
    =
    Session.DeviceId

lorsque la politique exige le binding.

Un changement d’appareil doit normalement passer par une nouvelle session.

---

## 68. Client binding

L’élévation peut être liée à :

    ClientApplicationId

Une preuve obtenue dans une application ne doit pas nécessairement être réutilisable dans une autre application.

---

## 69. Changement de réseau

Un changement de réseau n’interdit pas nécessairement l’élévation, mais peut :

- augmenter le risque ;
- réduire la durée ;
- imposer une méthode plus forte ;
- déclencher une notification ;
- provoquer un refus.

---

## 70. Self-service elevation

Dans le cas ordinaire :

    ActorUserId = Session.UserId

Le `User` renforce sa propre session.

Aucune permission métier n’est requise pour lancer l’authentification step-up.

L’action sensible demandée reste toutefois soumise à autorisation.

---

## 71. Élévation administrative

Une élévation ne doit pas permettre à un administrateur de renforcer silencieusement la session d’un autre utilisateur.

Une session appartient à son détenteur.

Pour une opération administrative :

    administrative actor
    authenticates own Session
    and performs authorized operation

Il ne doit pas élever directement la session d’un autre `User`.

---

## 72. Impersonation

Dans une session d’impersonation :

    ActorUserId != SubjectUserId

L’élévation doit authentifier l’acteur réel.

L’événement doit conserver les deux identités.

---

## 73. Données sensibles

La commande ne reçoit ni ne stocke :

- mot de passe brut ;
- code OTP brut ;
- secret TOTP ;
- recovery code ;
- credential WebAuthn brut ;
- clé privée ;
- token d’identité brut ;
- donnée biométrique brute ;
- réponse de challenge complète.

Ces données sont validées par le composant d’authentification qui produit `AuthenticationProof`.

---

## 74. Traitement métier

### 74.1 Vérifier l’idempotence

Le système recherche :

    SessionId + ElevateSessionRequestId

Une répétition identique retourne le résultat initial.

---

### 74.2 Charger la Session

Le système charge :

- `SessionId` ;
- `UserId` ;
- statut ;
- type ;
- contexte de base ;
- élévation actuelle ;
- dates ;
- appareil ;
- client ;
- versions de sécurité ;
- version de l’agrégat.

---

### 74.3 Vérifier le statut de la Session

Condition :

    Session.Status = Active

---

### 74.4 Vérifier l’expiration de la Session

Condition :

    ElevatedAt < Session.ExpiresAt

Une session expirée ne peut pas être élevée.

---

### 74.5 Charger le User

Le système charge :

    User.Status
    User.SecurityVersion
    User.AuthenticationStateVersion

---

### 74.6 Vérifier l’état du User

Le `User` doit être autorisé à utiliser la session.

---

### 74.7 Vérifier les versions de sécurité

Conditions :

    Session.UserSecurityVersion
    =
    User.SecurityVersion

et, lorsque présente :

    Session.AuthenticationStateVersion
    =
    User.AuthenticationStateVersion

---

### 74.8 Charger AuthenticationProof

Le système charge la preuve step-up.

---

### 74.9 Vérifier l’intégrité de la preuve

Le système vérifie :

- la source ;
- l’émetteur ;
- l’intégrité ;
- la signature ou référence de confiance ;
- la date de création ;
- la date d’expiration ;
- le sujet ;
- le workflow ;
- la session ;
- l’intention ;
- les versions de sécurité.

---

### 74.10 Vérifier l’association au User

Condition :

    AuthenticationProof.UserId
    =
    Session.UserId

Pour une impersonation, la politique vérifie l’acteur réel.

---

### 74.11 Vérifier l’association à la Session

Lorsque requis :

    AuthenticationProof.SessionId
    =
    Session.SessionId

---

### 74.12 Vérifier l’association à l’intention

Lorsque requis :

    AuthenticationProof.IntentionId
    =
    IntentionId

---

### 74.13 Vérifier la validité temporelle

Conditions :

    ElevatedAt <= AuthenticationProof.ValidUntil

et :

    ElevatedAt - AuthenticationProof.AuthenticatedAt
    <= Policy.MaximumAuthenticationAge

---

### 74.14 Vérifier l’absence de consommation

Pour une preuve à usage unique :

    AuthenticationProof is not consumed

---

### 74.15 Charger ElevationPolicy

Le système charge la politique à partir de :

- `ElevationPolicyId` ;
- l’action ;
- la permission ;
- le type de session ;
- le risque ;
- le workspace ;
- le produit.

---

### 74.16 Vérifier le type de Session

Le type de session doit être autorisé par la politique.

---

### 74.17 Vérifier le niveau d’assurance

Condition :

    AuthenticationProof.AssuranceLevel
    >=
    Policy.MinimumAssuranceLevel

---

### 74.18 Vérifier l’augmentation effective

Condition :

    NewEffectiveAssuranceLevel
    >
    CurrentBaseAssuranceLevel

ou, si une élévation existe :

    new elevation provides a distinct stronger or scoped result

---

### 74.19 Vérifier les méthodes

Le système vérifie :

    Policy.RequiredAuthenticationMethods
    subset of
    AuthenticationProof.AuthenticationMethodSet

---

### 74.20 Vérifier les propriétés des méthodes

Exemples :

- user verification ;
- phishing resistance ;
- hardware-backed credential ;
- verified origin ;
- managed device ;
- certificate assurance.

---

### 74.21 Vérifier la portée

La portée demandée doit être autorisée.

---

### 74.22 Vérifier l’intention

L’intention doit être cohérente avec :

- l’action ;
- le workspace ;
- la ressource ;
- la permission ;
- le workflow.

---

### 74.23 Vérifier l’appareil

Lorsque requis :

    AuthenticationProof.DeviceId
    =
    Session.DeviceId

---

### 74.24 Vérifier le client

Lorsque requis :

    AuthenticationProof.ClientApplicationId
    =
    Session.ClientApplicationId

---

### 74.25 Vérifier le risque

Le risque doit être compatible avec la politique.

---

### 74.26 Analyser l’élévation existante

Le système détermine :

- si une élévation existe ;
- si elle est encore active ;
- si elle doit être remplacée ;
- si la nouvelle élévation est plus forte ;
- si la portée entre en conflit ;
- si une preuve supplémentaire est nécessaire.

---

### 74.27 Calculer ExpiresAt

Le système calcule :

    ElevationExpiresAt
    =
    min(
      ElevatedAt + Policy.MaximumElevationLifetime,
      Session.ExpiresAt
    )

---

### 74.28 Calculer MaximumActionCount

La politique détermine :

    MaximumActionCount

Pour une élévation illimitée dans sa courte durée :

    MaximumActionCount = null

Pour une élévation à usage unique :

    MaximumActionCount = 1

---

### 74.29 Construire SessionElevation

Le système crée :

    SessionElevation
    ├── Status: Active
    ├── AssuranceLevel
    ├── AuthenticationMethodSet
    ├── ElevatedAt
    ├── ExpiresAt
    ├── MaximumActionCount
    ├── RemainingActionCount
    ├── ElevationScope
    ├── IntentionId
    ├── AuthenticationProofId
    ├── ElevationPolicyId
    ├── RiskAssessmentId
    └── ElevationVersion

---

### 74.30 Remplacer l’élévation précédente

Lorsque la politique l’autorise, l’ancienne élévation devient terminale ou est remplacée dans l’état courant.

Son historique reste dans les événements et l’audit.

---

### 74.31 Consommer AuthenticationProof

Lorsque la preuve est à usage unique :

    AuthenticationProof
    → Consumed

Cette consommation doit être atomique avec l’élévation.

---

### 74.32 Incrémenter ElevationVersion

    Session.ElevationVersion += 1

---

### 74.33 Incrémenter SessionSecurityVersion

    Session.SessionSecurityVersion += 1

---

### 74.34 Incrémenter Session.Version

    Session.Version += 1

---

### 74.35 Produire SessionElevated

L’agrégat produit :

    SessionElevated

---

### 74.36 Enregistrer l’idempotence

Le résultat est associé à :

    ElevateSessionRequestId

---

### 74.37 Commit atomique

Le même commit logique doit contenir :

    SessionElevation activated
    +
    previous elevation replaced when applicable
    +
    AuthenticationProof consumed
    +
    SessionSecurityVersion incremented
    +
    Session.Version incremented
    +
    idempotency record
    +
    SessionElevated event

---

## 75. Résultat attendu

Après succès :

    Session
    ├── same SessionId
    ├── same UserId
    ├── Status: Active
    ├── same SessionType
    ├── same base authentication context
    ├── Elevation
    │   ├── Status: Active
    │   ├── stronger AssuranceLevel
    │   ├── explicit scope
    │   ├── ElevatedAt
    │   ├── bounded ExpiresAt
    │   ├── AuthenticationProofId
    │   └── ElevationVersion incremented
    ├── SessionSecurityVersion incremented
    └── Session.Version incremented

---

## 76. Résultat fonctionnel

Structure recommandée :

    ElevateSessionResult
    ├── SessionId
    ├── UserId
    ├── SessionType
    ├── BaseAssuranceLevel
    ├── ElevatedAssuranceLevel
    ├── EffectiveAssuranceLevel
    ├── ElevationScope
    ├── IntentionId
    ├── WorkspaceId
    ├── RequiredAction
    ├── ResourceType
    ├── ResourceId
    ├── ElevatedAt
    ├── ExpiresAt
    ├── MaximumActionCount
    ├── RemainingActionCount
    ├── ElevationVersion
    ├── SessionSecurityVersion
    └── SessionVersion

Le résultat ne contient aucun secret d’authentification.

---

## 77. Invariants concernés

### Session active

    Session elevation
    requires Session.Status = Active

---

### Identité stable

    SessionId remains unchanged

---

### User stable

    Session.UserId remains unchanged

---

### SessionType stable

    Session.SessionType remains unchanged

---

### Élévation temporaire

    Elevation.ExpiresAt
    >
    Elevation.ElevatedAt

---

### Élévation bornée par la Session

    Elevation.ExpiresAt
    <=
    Session.ExpiresAt

---

### Assurance suffisante

    Elevation.AssuranceLevel
    >=
    Policy.MinimumAssuranceLevel

---

### Preuve liée au User

    AuthenticationProof.UserId
    =
    Session.UserId

---

### Preuve suffisamment récente

    ElevatedAt - AuthenticatedAt
    <= MaximumAuthenticationAge

---

### Preuve consommée au plus une fois

    SingleUse AuthenticationProof
    is consumed at most once

---

### Aucune permission directe

    SessionElevation
    does not grant Permission

---

### Autorisation dynamique

    elevated assurance
    does not replace Workspace authorization

---

### Version de sécurité

    Session security version changes
    after elevation

---

### Élévation terminale non réactivée silencieusement

Une élévation expirée ou révoquée exige une nouvelle preuve pour redevenir active.

---

## 78. Événement produit

### SessionElevated

Contenu recommandé :

- `SessionId`
- `UserId`
- `ActorUserId`
- `SubjectUserId`
- `SessionType`
- `BaseAssuranceLevel`
- `PreviousEffectiveAssuranceLevel`
- `ElevatedAssuranceLevel`
- `CurrentEffectiveAssuranceLevel`
- `AuthenticationMethods`
- `ElevationScope`
- `IntentionId`
- `WorkspaceId`
- `RequiredAction`
- `RequiredPermissionId`
- `ResourceType`
- `ResourceId`
- `ElevatedAt`
- `ExpiresAt`
- `MaximumActionCount`
- `PreviousElevationReplaced`
- `AuthenticationProofId`
- `ElevationPolicyId`
- `RiskAssessmentId`
- `DeviceAssessmentId`
- `DeviceId`
- `ClientApplicationId`
- `UserSecurityVersion`
- `AuthenticationStateVersion`
- `ElevationVersion`
- `SessionSecurityVersion`
- `SessionVersion`
- `ElevateSessionRequestId`
- `CorrelationId`

---

## 79. Données interdites dans SessionElevated

L’événement ne doit pas contenir :

- mot de passe ;
- code OTP ;
- secret TOTP ;
- recovery code ;
- access token ;
- refresh token ;
- cookie ;
- credential WebAuthn brut ;
- assertion biométrique brute ;
- clé privée ;
- réponse de challenge complète ;
- données confidentielles inutiles ;
- contenu complet d’un fournisseur d’identité.

---

## 80. Effets et signaux secondaires possibles

Après succès :

    SessionElevationActivated
    SensitiveActionAuthorizationReevaluationRequested
    SessionSecurityProjectionUpdated
    SessionElevationExpirationScheduled
    PrivilegedSessionNotificationRequested

Lors du remplacement d’une élévation précédente :

    PreviousSessionElevationTerminated

Lors d'une tentative refusée, un signal de sécurité restreint peut être
enregistré sans Domain Event.

---

## 81. Tentative d'élévation refusée

Un signal de sécurité ou un enregistrement d'audit peut être produit lorsque
l'échec est significatif. Il ne fait pas partie des Domain Events.

Exemples :

- preuve invalide ;
- preuve destinée à une autre session ;
- preuve rejouée ;
- niveau d’assurance insuffisant ;
- risque critique ;
- appareil incohérent ;
- tentative pendant une impersonation interdite.

Contenu recommandé :

- `SessionId`
- `UserId`
- `AuthenticationProofId`
- `ElevationPolicyId`
- `RejectedAt`
- `RejectionReason`
- `RiskLevel`
- `DeviceId`
- `ClientApplicationId`
- `CorrelationId`

Aucun secret.

---

## 82. Expiration de l’élévation

À l’échéance :

    CurrentTime >= Elevation.ExpiresAt

l’élévation devient inefficace.

L'évaluation temporelle rend immédiatement l'élévation inefficace, même avant
la matérialisation persistante. Un traitement temporel produit ensuite :

    SessionElevationExpired

---

## 83. Recommandation

La validité doit toujours être déterminée dynamiquement :

    CurrentTime < ExpiresAt

Une transition explicite peut ensuite mettre à jour l’état pour :

- l’audit ;
- les projections ;
- le nettoyage ;
- les interfaces ;
- les notifications.

La sécurité ne doit pas dépendre du passage d’un scheduler.

---

## 84. SessionElevationExpired

Événement recommandé lors de la matérialisation de l’expiration :

- `SessionId`
- `UserId`
- `ElevationVersion`
- `ElevatedAt`
- `ExpiredAt`
- `PreviousAssuranceLevel`
- `CurrentEffectiveAssuranceLevel`
- `ExpirationReason`
- `SessionSecurityVersion`
- `SessionVersion`

---

## 85. Révocation de l’élévation

Une élévation peut être révoquée avant son expiration en raison :

- d’un incident ;
- d’un changement de risque ;
- d’une action consommée ;
- d’un changement de sécurité du `User` ;
- d’une révocation de session ;
- d’une fermeture de workflow ;
- d’une modification d’appareil ;
- d’une décision administrative.

`ExpireSessionElevation` matérialise l'échéance. `TerminateSessionElevation`
porte toute fin anticipée sans révocation obligatoire de la session.

---

## 86. Effet de RevokeSession

Lorsque la session principale est révoquée :

    Session.Status = Revoked

toute élévation devient immédiatement inefficace.

Il n’est pas nécessaire d’attendre son expiration.

---

## 87. Effet de ExpireSession

Lorsque la session expire :

    Session.Status = Expired

l’élévation expire également.

---

## 88. Effet de RevokeAllUserSessions

La révocation globale invalide :

- la session ;
- l’élévation ;
- les refresh credentials ;
- les access tokens dérivés.

---

## 89. Effet d’un changement de sécurité du User

Si :

    User.SecurityVersion
    changes

alors l’élévation devient invalide, même si :

    CurrentTime < Elevation.ExpiresAt

Le moteur doit vérifier la version courante.

---

## 90. Effet d’un changement de Permission

Une élévation peut rester active.

Mais une permission retirée ne peut plus être utilisée.

    Permission revoked
    +
    Session still elevated
            ↓
    authorization denied

---

## 91. Effet d’un changement de Role

Même principe.

L’élévation ne conserve pas les anciennes capacités du rôle.

---

## 92. Effet d’une suspension du Membership

La session globale peut rester active et élevée.

Mais l’accès au workspace concerné devient indisponible.

---

## 93. Effet d’un Role désactivé ou archivé

L’élévation ne permet pas de contourner l’état du rôle.

    Role.Status != Active
            ↓
    Permission ineffective

---

## 94. Claims d’access token

Une élévation peut être reflétée dans un access token dérivé.

Claims possibles :

    assurance_level
    authentication_time
    elevation_expires_at
    elevation_scope
    elevation_version
    session_security_version

Ces claims ne sont pas une source de vérité indépendante.

---

## 95. Token émis avant élévation

Un access token émis avant l’élévation ne doit pas être interprété comme élevé.

Après succès, le client peut nécessiter :

    new access token issuance

Le domaine peut produire une indication :

    AccessCredentialReissuanceRequired = true

---

## 96. Token émis pendant l’élévation

Le token doit expirer au plus tard à :

    min(
      AccessTokenStandardExpiry,
      Elevation.ExpiresAt,
      Session.ExpiresAt
    )

Un token privilégié ne doit pas survivre à l’élévation.

---

## 97. Refresh pendant une élévation

`RefreshSession` ne doit pas prolonger automatiquement l’élévation.

Après un refresh :

    Elevation.ExpiresAt remains unchanged

Si l’élévation a expiré :

    refreshed access token
    must not include elevated assurance

---

## 98. Nouvelle élévation après refresh

Une nouvelle `AuthenticationProof` est nécessaire.

Le refresh credential seul ne suffit pas.

---

## 99. Idempotence

Clé recommandée :

    SessionId + ElevateSessionRequestId

---

## 100. Empreinte idempotente

L’empreinte doit inclure au minimum :

    SessionId
    AuthenticationProofId
    ElevationPolicyId
    ElevationScope
    IntentionId
    WorkspaceId
    RequiredAction
    RequiredPermissionId
    ResourceType
    ResourceId
    RequestedLifetime
    MaximumActionCount
    DeviceId
    ClientApplicationId

---

## 101. Répétition identique

Une répétition exacte retourne le résultat initial sans :

- consommer une seconde fois la preuve ;
- créer une nouvelle élévation ;
- prolonger l’expiration ;
- incrémenter une nouvelle version ;
- produire un second événement ;
- demander une seconde émission de credentials ;
- envoyer une seconde notification.

---

## 102. Preuve déjà consommée par la même demande

Le résultat idempotent initial est retourné.

Le système ne doit pas répondre :

    AuthenticationProofAlreadyConsumed

pour un retry strictement identique ayant déjà réussi.

---

## 103. Preuve consommée par une autre demande

La commande échoue avec :

    AuthenticationProofAlreadyConsumed

ou :

    AuthenticationProofReplayDetected

selon le contexte de sécurité.

---

## 104. Conflit d’idempotence

Le même `ElevateSessionRequestId` avec une autre intention produit :

    IdempotencyConflict

---

## 105. Reprise après réponse perdue

Cas :

    ElevateSession succeeds
            ↓
    transaction commits
            ↓
    response is lost
            ↓
    caller retries

Le retry retourne :

- la même élévation ;
- le même `ElevatedAt` ;
- le même `ExpiresAt` ;
- la même portée ;
- les mêmes versions ;
- le même résultat logique.

---

## 106. Concurrence

### 106.1 Deux élévations concurrentes avec la même preuve

Une seule commande peut consommer la preuve.

L’autre :

- reçoit le résultat idempotent si l’intention est identique ;
- échoue si l’intention est différente.

---

### 106.2 Deux preuves différentes

Deux commandes peuvent tenter de remplacer simultanément l’élévation.

Une seule version de la session gagne.

L’autre doit recharger et réévaluer.

---

### 106.3 Elevate contre RevokeSession

Si la révocation gagne :

    SessionRevoked

Si l’élévation gagne, la révocation suivante invalide immédiatement l’élévation.

---

### 106.4 Elevate contre ExpireSession

Une session expirée ne peut pas être élevée.

---

### 106.5 Elevate contre RefreshSession

Les deux commandes modifient la session.

Une version optimiste doit protéger les mises à jour.

Le refresh ne doit pas effacer ou prolonger silencieusement l’élévation.

---

### 106.6 Elevate contre changement de mot de passe

Si `User.SecurityVersion` change avant le commit :

    UserSecurityVersionConflict

---

### 106.7 Elevate contre suspension du User

Si la désactivation gagne :

    UserDisabled

---

### 106.8 Elevate contre RevokeAllUserSessions

La révocation globale doit empêcher ou invalider l’élévation.

---

### 106.9 Elevate contre consommation de l’élévation

Une action sensible peut consommer une élévation à usage unique pendant qu’une nouvelle élévation est créée.

La version d’élévation doit protéger ce cas.

---

### 106.10 Elevate contre changement de politique

Si la politique change entre validation et commit, une version de politique peut être vérifiée.

---

## 107. ExpectedSessionVersion

Condition :

    Session.Version
    =
    ExpectedSessionVersion

lorsque fournie.

---

## 108. ExpectedSessionSecurityVersion

Condition :

    Session.SessionSecurityVersion
    =
    ExpectedSessionSecurityVersion

Cette version protège les changements liés à la sécurité sans dépendre uniquement de la version générale.

---

## 109. ExpectedUserSecurityVersion

Condition :

    User.SecurityVersion
    =
    ExpectedUserSecurityVersion

La preuve doit également capturer la même version.

---

## 110. Atomicité

Le même commit logique doit garantir :

    AuthenticationProof consumed
    +
    SessionElevation activated
    +
    previous elevation replaced when applicable
    +
    ElevationVersion incremented
    +
    SessionSecurityVersion incremented
    +
    Session.Version incremented
    +
    idempotency record
    +
    SessionElevated event

---

## 111. États interdits

    Revoked Session
    → elevated

    Expired Session
    → elevated

    Removed User
    → Session elevated

    Disabled User
    → ordinary Session elevated

    AuthenticationProof belongs to another User
    → elevation succeeds

    AuthenticationProof belongs to another Session
    → elevation succeeds

    expired AuthenticationProof
    → elevation succeeds

    consumed AuthenticationProof
    → new elevation succeeds

    insufficient assurance
    → elevation succeeds

    required authentication method missing
    → elevation succeeds

    Elevation.ExpiresAt > Session.ExpiresAt

    Elevation without bounded ExpiresAt

    ElevateSession changes SessionId

    ElevateSession changes UserId

    ElevateSession changes SessionType

    ElevateSession grants Permission

    ElevateSession restores Membership access

    raw authentication secret appears in event

    Session elevation succeeds
    without SessionElevated event

---

## 112. Outbox transactionnelle

`SessionElevated` doit être enregistré dans la même transaction que la modification de la session.

Sa publication intervient après commit.

---

## 113. Effets externes

Après succès, des handlers peuvent :

- émettre un nouvel access token ;
- invalider les anciens claims d’assurance ;
- mettre à jour les projections de session ;
- programmer l’expiration de l’élévation ;
- mettre à jour l’historique de sécurité ;
- notifier le `User` ;
- reprendre le workflow sensible ;
- mettre à jour un challenge d’autorisation ;
- enregistrer la consommation de la preuve ;
- alimenter un système de détection de risque.

---

## 114. Notifications

Une notification peut être recommandée lorsque :

- l’élévation atteint `VeryHigh` ;
- une opération critique est visée ;
- l’élévation est utilisée pour une impersonation ;
- un appareil inhabituel est utilisé ;
- le risque est élevé ;
- un facteur inhabituel est utilisé ;
- l’élévation intervient après une récupération de compte.

Une notification n’est pas nécessaire pour chaque step-up ordinaire.

---

## 115. Audit

Une élévation réussie doit permettre de connaître :

- la session concernée ;
- le `User` concerné ;
- l’acteur réel ;
- le sujet représenté ;
- le type de session ;
- le niveau d’assurance de base ;
- le niveau d’assurance obtenu ;
- les méthodes d’authentification utilisées ;
- la date d’authentification ;
- la date d’élévation ;
- la date d’expiration ;
- la portée ;
- l’intention ;
- le workspace ;
- l’action ;
- la permission contextuelle ;
- la ressource ;
- le nombre d’utilisations autorisé ;
- la politique appliquée ;
- la preuve consommée ;
- le niveau de risque ;
- l’appareil ;
- le client ;
- les versions de sécurité ;
- la demande idempotente ;
- le workflow corrélé.

---

## 116. Questions auxquelles l’audit doit répondre

    which Session was elevated
    for which User
    who actually authenticated
    whether actor and subject were different
    which assurance level existed before
    which assurance level was reached
    which authentication methods were used
    when the step-up authentication occurred
    when the elevation began
    when the elevation expires
    which actions or resources are covered
    which Workspace is covered
    which policy required the elevation
    which AuthenticationProof was consumed
    what risk level was observed
    which device and client were involved
    which Session security version became effective

---

## 117. Confidentialité

Les données suivantes doivent être minimisées :

- adresse IP ;
- localisation ;
- user agent ;
- nom d’appareil ;
- fournisseur d’identité ;
- identifiant externe ;
- détail du risque ;
- détail du challenge.

La preuve brute et les secrets ne doivent jamais apparaître dans l’audit.

---

## 118. Sécurité

La commande doit garantir que :

- seule une session active peut être élevée ;
- une session révoquée ne peut pas être réactivée ;
- une session expirée ne peut pas être prolongée ;
- le `User` reste valide ;
- les versions de sécurité correspondent ;
- la preuve appartient au bon `User` ;
- la preuve appartient à la bonne session ;
- la preuve correspond à l’intention lorsque requis ;
- la preuve est suffisamment récente ;
- le niveau d’assurance est suffisant ;
- les méthodes requises sont présentes ;
- les propriétés de sécurité des méthodes sont vérifiées ;
- le risque est acceptable ;
- l’appareil et le client sont cohérents ;
- l’élévation est courte ;
- l’élévation ne dépasse pas la session ;
- la portée est explicite ;
- les permissions restent vérifiées séparément ;
- la preuve à usage unique n’est consommée qu’une fois ;
- l’opération est idempotente ;
- la concurrence ne crée pas deux élévations incohérentes ;
- aucun secret n’entre dans les événements ;
- l’élévation et l’événement sont persistés atomiquement.

---

## 119. Erreurs métier

### SessionNotFound

La session n’existe pas.

---

### SessionNotActive

La session n’est pas active.

---

### SessionRevoked

La session est révoquée.

---

### SessionExpired

La session est expirée.

---

### UserNotFound

Le `User` n’existe pas.

---

### UserPending

Le `User` ne peut utiliser qu’un workflow restreint.

---

### UserDisabled

Le `User` est désactivé.

---

### AuthenticationTemporarilyLocked

Le point d'authentification du `User` est temporairement verrouillé.

---

### UserDisabled

Le `User` est désactivé.

---

### UserRemoved

Le `User` a été supprimé.

---

### UserSecurityVersionConflict

La version de sécurité du `User` a changé.

---

### AuthenticationStateVersionConflict

L’état d’authentification du `User` a changé.

---

### AuthenticationProofNotFound

La preuve n’existe pas.

---

### AuthenticationProofInvalid

La preuve est invalide.

---

### AuthenticationProofExpired

La preuve a expiré.

---

### AuthenticationProofAlreadyConsumed

La preuve à usage unique a déjà été consommée.

---

### AuthenticationProofReplayDetected

La preuve semble avoir été rejouée.

---

### AuthenticationProofIntegrityViolation

L’intégrité de la preuve n’est pas valide.

---

### AuthenticationProofSourceNotTrusted

La preuve provient d’une source non autorisée.

---

### AuthenticationProofUserMismatch

La preuve appartient à un autre `User`.

---

### AuthenticationProofSessionMismatch

La preuve appartient à une autre session.

---

### AuthenticationProofIntentionMismatch

La preuve ne couvre pas l’intention demandée.

---

### AuthenticationFlowMismatch

La preuve appartient à un autre workflow.

---

### AuthenticationTooOld

L’authentification n’est plus assez récente.

---

### AuthenticationAssuranceInsufficient

Le niveau d’assurance est insuffisant.

---

### ElevationDoesNotIncreaseAssurance

La preuve n’améliore pas l’assurance effective.

---

### RequiredAuthenticationMethodMissing

Une méthode obligatoire n’a pas été utilisée.

---

### RequiredMethodPropertyMissing

Une propriété de sécurité obligatoire est absente.

---

### PhishingResistantAuthenticationRequired

Une méthode résistante au phishing est obligatoire.

---

### UserVerificationRequired

La preuve ne démontre pas une vérification suffisante du `User`.

---

### MultiFactorAuthenticationRequired

Une authentification multifacteur est requise.

---

### ElevationPolicyNotFound

La politique d’élévation n’existe pas.

---

### ElevationNotAllowed

L’élévation est interdite.

---

### SessionTypeNotElevatable

Le type de session ne peut pas être élevé.

---

### ElevationScopeNotAllowed

La portée demandée n’est pas autorisée.

---

### ElevationLifetimeNotAllowed

La durée demandée dépasse la politique.

---

### ElevationActionCountNotAllowed

Le nombre d’utilisations demandé dépasse la politique.

---

### ElevationIntentionRequired

Une intention explicite est obligatoire.

---

### ElevationWorkspaceRequired

Un workspace doit être lié à l’élévation.

---

### ElevationActionRequired

Une action sensible doit être précisée.

---

### ElevationResourceRequired

Une ressource doit être liée à l’élévation.

---

### ElevationAlreadyActive

Une élévation active empêche cette opération selon la politique.

---

### StrongerElevationAlreadyActive

La session possède déjà une élévation plus forte.

---

### ElevationReplacementNotAllowed

La politique interdit de remplacer l’élévation courante.

---

### DeviceBindingMismatch

La preuve provient d’un autre appareil.

---

### ClientBindingMismatch

La preuve provient d’une autre application cliente.

---

### DeviceTrustInsufficient

Le niveau de confiance de l’appareil est insuffisant.

---

### DeviceCompromised

L’appareil est considéré comme compromis.

---

### ElevationRiskTooHigh

Le risque interdit l’élévation.

---

### RiskAssessmentRequired

Une évaluation de risque est obligatoire.

---

### RiskAssessmentInvalid

L’évaluation de risque fournie n’est pas valide.

---

### ImpersonationElevationNotAllowed

L’élévation est interdite pendant une impersonation.

---

### ImpersonationActorMismatch

La preuve n’appartient pas à l’acteur réel de l’impersonation.

---

### RecoverySessionElevationNotAllowed

La session de récupération ne peut pas être élevée pour cette action.

---

### ServiceSessionElevationNotAllowed

Une session de service ne peut pas être élevée avec cette commande.

---

### FederatedElevationNotAllowed

La politique interdit l’élévation de cette session fédérée.

---

### IdentityProviderAssuranceInsufficient

Le fournisseur d’identité ne fournit pas une assurance suffisante.

---

### SessionVersionConflict

La session a changé depuis la lecture initiale.

---

### SessionSecurityVersionConflict

L’état de sécurité de la session a changé.

---

### ElevationVersionConflict

L’élévation a changé de manière concurrente.

---

### ElevationConcurrencyConflict

Une opération concurrente empêche l’élévation.

---

### IdempotencyConflict

Le même identifiant représente une autre intention.

---

## 120. Décisions de conception

### ElevateSession conserve le SessionId

L’élévation renforce une session existante.

---

### ElevateSession conserve le UserId

La session ne change pas de sujet.

---

### ElevateSession conserve le SessionType

Une transformation de type nécessite une commande distincte.

---

### L’élévation est temporaire

Elle possède toujours une expiration bornée.

---

### L’élévation ne survit pas à la Session

    Elevation.ExpiresAt <= Session.ExpiresAt

---

### Une nouvelle AuthenticationProof est obligatoire

Le refresh credential ne suffit pas.

---

### La preuve est liée à la Session

Cette liaison limite les réutilisations intersessions.

---

### La preuve peut être liée à l’intention

Les actions critiques utilisent une portée réduite.

---

### L’élévation n’accorde aucune Permission

L’autorisation métier reste dynamique.

---

### Une seule élévation active est conservée

Une nouvelle élévation remplace explicitement la précédente selon la politique.

---

### Aucune prolongation silencieuse

Une nouvelle preuve est nécessaire pour prolonger ou renouveler l’élévation.

---

### Les élévations critiques peuvent être à usage unique

La consommation doit être atomique avec l’action sensible.

---

### L’expiration est vérifiée dynamiquement

La sécurité ne dépend pas d’un scheduler.

---

### SessionSecurityVersion est incrémentée

Les credentials et projections obsolètes peuvent être détectés.

---

### La preuve brute reste hors du domaine

Seule sa référence validée est utilisée.

---

### Un événement métier dédié est produit

    SessionElevated

---

## 121. Cas limites

### Session active sans Membership

La session peut être élevée.

Cela ne crée aucun accès à un workspace.

---

### Session active avec Membership suspendu

La session peut éventuellement être élevée globalement.

L’accès au workspace suspendu reste refusé.

---

### Session active avec Role désactivé

L’élévation peut réussir, mais le rôle reste inefficace.

---

### Preuve expirant exactement au moment du commit

La validité doit être vérifiée dans le contexte transactionnel.

---

### Preuve déjà consommée par un retry identique

Le résultat idempotent initial est retourné.

---

### Preuve consommée par une autre intention

La commande échoue et peut déclencher une alerte de sécurité.

---

### Session déjà élevée à un niveau inférieur

La nouvelle élévation peut remplacer la précédente.

---

### Session déjà élevée à un niveau supérieur

La nouvelle élévation est refusée ou ignorée selon la politique.

---

### Nouvelle élévation avec une portée différente

Le système doit refuser toute fusion implicite.

La portée finale doit être explicite.

---

### Élévation demandée pour plusieurs workspaces

Une portée globale multi-workspaces est déconseillée pour les actions critiques.

Une élévation distincte par contexte peut être exigée.

---

### Élévation demandée sans intention

Elle peut être autorisée pour une courte élévation `SessionWide`, mais pas pour les opérations critiques exigeant un binding précis.

---

### Permission retirée après l’élévation

L’élévation reste éventuellement active, mais l’action est refusée.

---

### Session rafraîchie après élévation

L’élévation conserve son expiration initiale.

---

### Session révoquée juste après élévation

La révocation invalide immédiatement l’élévation.

---

### Session expirant avant l’élévation calculée

L’expiration de l’élévation est limitée à celle de la session.

---

### Utilisation simultanée d’une élévation SingleUse

Une seule action doit pouvoir consommer l’élévation.

---

### Impersonation de même User

La session doit être normalisée ou refusée avant l’élévation.

---

### Élévation depuis un appareil différent

La commande est refusée lorsque le binding est requis.

---

### Fédération avec assurance obsolète

Une nouvelle authentification auprès du fournisseur ou un facteur local est nécessaire.

---

### Horloges légèrement désynchronisées

Une tolérance technique limitée peut être appliquée.

Elle doit être centralisée et ne pas prolonger artificiellement l’élévation.

---

## 122. Checklist de validation

Avant commit :

    Session exists
    Session.Status is Active
    Session has not expired
    User exists
    User status allows elevation
    UserSecurityVersion matches
    AuthenticationStateVersion matches
    AuthenticationProof exists
    AuthenticationProof belongs to User
    AuthenticationProof belongs to Session when required
    AuthenticationProof matches Intention when required
    AuthenticationProof source is trusted
    AuthenticationProof integrity is valid
    AuthenticationProof is not expired
    AuthenticationProof is sufficiently recent
    AuthenticationProof has not been consumed
    AuthenticationProof assurance is sufficient
    Required authentication methods are present
    Required method properties are satisfied
    Requested elevation increases or meaningfully specializes assurance
    ElevationPolicy exists
    SessionType is allowed
    ElevationScope is allowed
    Intention is valid
    Workspace binding is valid when required
    Action binding is valid when required
    Resource binding is valid when required
    Device binding is valid when required
    Client binding is valid when required
    Risk policy is satisfied
    Existing elevation replacement is allowed
    Elevation lifetime is bounded
    Elevation expiry does not exceed Session expiry
    MaximumActionCount is valid
    ExpectedSessionVersion matches
    ExpectedSessionSecurityVersion matches
    ExpectedUserSecurityVersion matches
    Idempotency is verified
    AuthenticationProof can be consumed atomically
    ElevationVersion can be incremented
    SessionSecurityVersion can be incremented
    No Permission is granted
    No raw authentication secret enters the domain
    SessionElevated can be persisted atomically

---

## 123. Synthèse

`ElevateSession` renforce temporairement le contexte d’authentification d’une session active afin de permettre l’évaluation d’opérations sensibles.

Elle garantit que :

- la session existe ;
- la session reste active ;
- le `SessionId` ne change pas ;
- le `UserId` ne change pas ;
- le `SessionType` ne change pas ;
- le `User` reste valide ;
- les versions de sécurité correspondent ;
- une nouvelle preuve step-up est fournie ;
- la preuve appartient au bon `User` ;
- la preuve appartient à la bonne session ;
- la preuve couvre l’intention lorsque nécessaire ;
- la preuve est intègre et non expirée ;
- la preuve est suffisamment récente ;
- la preuve n’a pas déjà été consommée ;
- le niveau d’assurance obtenu satisfait la politique ;
- les méthodes requises ont été utilisées ;
- les propriétés de sécurité exigées sont satisfaites ;
- l’appareil, le client et le risque sont acceptables ;
- l’élévation possède une portée explicite ;
- l’élévation possède une durée courte et bornée ;
- l’élévation ne dépasse jamais la durée de la session ;
- une élévation à usage unique peut être consommée atomiquement ;
- aucune permission n’est créée ;
- aucune autorisation de workspace n’est contournée ;
- les claims restent dérivés de l’état courant ;
- les anciens credentials élevés peuvent être invalidés par version ;
- l’opération est idempotente ;
- les conflits de concurrence sont détectés ;
- aucun secret d’authentification n’entre dans les événements ;
- l’élévation et son événement sont persistés atomiquement.

Le résultat conceptuel est :

    Session
    ├── same SessionId
    ├── same UserId
    ├── Status: Active
    ├── same SessionType
    ├── unchanged base authentication context
    ├── temporary SessionElevation
    │   ├── stronger assurance
    │   ├── explicit scope
    │   ├── explicit intention
    │   ├── bounded lifetime
    │   ├── optional action limit
    │   └── consumed AuthenticationProof
    ├── dynamic Workspace authorization
    ├── incremented ElevationVersion
    ├── incremented SessionSecurityVersion
    └── independently expirable elevation
