---
id: IDN-CMD-REVOKE-SESSION
title: RevokeSession
status: Draft
owner: Product
version: 1.0.0
last_updated: 2026-08-05

aggregate: Session

references:
  - README.md
  - ../entities.md
  - ../aggregates.md
  - ../relationships.md
  - ../value-objects.md
  - ../invariants.md
  - ../permissions.md
  - ../events/SessionRevoked.md
  - ../events/SessionRevocationRejected.md
  - CreateSession.md
  - RefreshSession.md
  - ElevateSession.md
  - RevokeAllUserSessions.md
  - ExpireSession.md
  - ../workflows.md
  - ../decision-record.md
---

# RevokeSession

## 1. Objectif

La commande `RevokeSession` met fin de manière explicite et irréversible à une `Session` existante.

Transition principale :

    Active
        ↓
    RevokeSession
        ↓
    Revoked

La révocation empêche immédiatement la session de participer à toute nouvelle décision d’authentification ou d’autorisation.

Elle invalide également :

- les refresh credentials associés ;
- la famille de refresh tokens ;
- l’élévation active éventuelle ;
- les access tokens dérivés selon la stratégie de validation ;
- les claims de session obsolètes ;
- les capacités de renouvellement de la session.

La commande garantit que :

- la session existe ;
- la session appartient au `User` attendu lorsque cette information est fournie ;
- l’acteur ou le workflow est autorisé à la révoquer ;
- la session ne peut plus être rafraîchie ;
- la session ne peut plus être élevée ;
- la session ne peut plus redevenir active ;
- les credentials de refresh deviennent inutilisables ;
- l’élévation éventuelle devient immédiatement inefficace ;
- les caches et projections peuvent détecter la révocation ;
- aucun secret n’apparaît dans les événements ;
- l’opération est idempotente ;
- la révocation et l’événement sont persistés atomiquement.

---

## 2. Intention métier

La commande répond à l’intention suivante :

    permanently terminate one existing Session
    so it can no longer authenticate or authorize requests

Elle représente une décision explicite de fin de session.

Elle ne signifie pas :

    suspend the User

ni :

    remove a Membership

ni :

    revoke a Role

ni :

    change a Permission

---

## 3. Distinction avec ExpireSession

`RevokeSession` correspond à une décision explicite :

    active Session
        ↓
    explicit revocation
        ↓
    Revoked

`ExpireSession` correspond à une fin liée au temps :

    active Session
        ↓
    lifetime exceeded
        ↓
    Expired

Les deux états sont terminaux, mais leur cause métier diffère.

---

## 4. Distinction avec RevokeAllUserSessions

`RevokeSession` cible exactement une session :

    one SessionId
        ↓
    one Session revoked

`RevokeAllUserSessions` cible l’ensemble des sessions d’un `User` :

    one UserId
        ↓
    all eligible Sessions revoked or globally invalidated

`RevokeSession` ne doit pas révoquer implicitement les autres sessions du même `User`.

---

## 5. Distinction avec la déconnexion

La déconnexion utilisateur ordinaire est une cause possible de révocation.

    User clicks Sign out
        ↓
    RevokeSession

La déconnexion n’est pas nécessairement un concept métier distinct si son effet attendu est exactement la révocation de la session courante.

---

## 6. Distinction avec une invalidation de credential

La révocation d’une session est plus large que l’invalidation d’un seul access token.

Une session peut posséder :

- plusieurs access tokens dérivés ;
- une famille de refresh credentials ;
- une élévation ;
- plusieurs projections de sécurité ;
- des caches d’autorisation.

La révocation concerne donc le contexte de session complet.

---

## 7. Agrégat concerné

    Session

La commande modifie un seul agrégat `Session`.

Elle peut consulter :

- le `User` ;
- l’acteur ;
- le propriétaire de la session ;
- les refresh credentials ;
- la famille de refresh tokens ;
- l’élévation active ;
- le contexte d’impersonation ;
- le contexte de récupération ;
- les politiques de révocation ;
- les informations de risque ;
- les règles administratives ;
- les versions de sécurité.

Elle ne modifie pas directement :

- le `User` ;
- le `Workspace` ;
- le `Membership` ;
- le `Role` ;
- la `Permission`.

---

## 8. Cycle de vie de Session

Cycle recommandé :

    Active
    ├── RevokeSession
    │       ↓
    │    Revoked
    │
    └── ExpireSession
            ↓
         Expired

États :

    Active
    Revoked
    Expired

Une session terminale ne peut pas redevenir active.

---

## 9. État Revoked

Une session `Revoked` :

- existe toujours ;
- conserve son identité ;
- conserve son `UserId` ;
- conserve son historique ;
- conserve ses métadonnées d’audit ;
- ne peut plus être utilisée ;
- ne peut plus être rafraîchie ;
- ne peut plus être élevée ;
- ne peut plus émettre de nouveaux credentials ;
- ne peut plus participer à une autorisation ;
- ne peut plus redevenir active.

---

## 10. Irréversibilité

Après succès :

    Session.Status = Revoked

Aucune commande ordinaire ne doit permettre :

    Revoked
        ↓
    Active

Une nouvelle authentification doit créer une nouvelle session :

    AuthenticationProof
        ↓
    CreateSession
        ↓
    new SessionId

---

## 11. Identité stable

La révocation ne modifie pas :

    SessionId
    UserId
    SessionType
    CreatedAt
    AuthenticatedAt

Elle modifie principalement :

    Status
    RevokedAt
    RevokedBy
    RevocationReason
    RevocationSource
    SessionSecurityVersion
    Session.Version

---

## 12. Effet sur l’autorisation

La chaîne normale est :

    Session
    → User
    → Membership
    → Role
    → Permission

Après révocation :

    Session.Status = Revoked
        ↓
    authorization denied

Le moteur doit refuser la requête avant même de poursuivre l’évaluation du membership ou du rôle.

---

## 13. Effet sur les Permissions

Aucune permission n’est modifiée.

La session perd seulement la capacité de présenter un contexte d’authentification valide.

---

## 14. Effet sur les Memberships

Aucun membership n’est modifié.

Le `User` peut continuer à posséder des memberships valides et ouvrir une nouvelle session si son état de compte et les politiques l’autorisent.

---

## 15. Effet sur les Roles

Aucun rôle n’est modifié.

La révocation de session ne désactive pas le rôle du `User`.

---

## 16. Effet sur le User

Le `User` n’est pas suspendu, verrouillé ou supprimé par `RevokeSession`.

La commande cible seulement une session.

---

## 17. Effet sur les access tokens

Les access tokens déjà émis doivent devenir inutilisables selon la stratégie d’architecture.

Stratégies possibles :

    introspection server-side
    session status lookup
    SessionSecurityVersion validation
    short-lived access tokens
    token revocation list
    credential family invalidation

---

## 18. Recommandation pour les access tokens

Le système doit au minimum vérifier :

    Session.Status
    Session.SessionSecurityVersion
    User.SecurityVersion

pour les actions sensibles.

Pour des tokens autonomes de courte durée, la révocation complète peut ne pas être instantanée sans introspection.

Cette limite doit être explicitement documentée.

---

## 19. Effet sur les refresh credentials

La révocation doit rendre inutilisable tout refresh credential lié à la session.

Condition :

    Session.Status = Revoked
        ↓
    RefreshSession forbidden

Même si un refresh credential semble encore cryptographiquement valide, la session terminale doit suffire à le refuser.

---

## 20. Révocation de la famille de refresh tokens

La famille liée à la session doit devenir :

    Revoked

ou, si une compromission est détectée :

    Compromised

Structure conceptuelle :

    RefreshTokenFamily
    ├── FamilyId
    ├── SessionId
    ├── Status
    ├── RevokedAt
    ├── RevocationReason
    └── Version

---

## 21. Effet sur l’élévation

Toute élévation active devient immédiatement inefficace.

    Session.Status = Revoked
        ↓
    SessionElevation ineffective

L’élévation peut être marquée :

    Revoked

ou simplement être considérée comme invalide parce que la session principale est terminale.

---

## 22. Recommandation pour l’élévation

Conserver l’historique de l’élévation, mais enregistrer explicitement :

    ElevationTerminatedBySessionRevocation = true

dans l’événement ou l’audit.

---

## 23. Acteurs possibles

La commande peut être initiée par :

- le propriétaire de la session ;
- le `User` depuis un écran de gestion de ses appareils ;
- un administrateur de sécurité ;
- un administrateur de plateforme ;
- un workflow de réponse à incident ;
- un système de détection de compromission ;
- un workflow de changement de mot de passe ;
- un workflow de récupération de compte ;
- un fournisseur d’identité ;
- une politique de sécurité ;
- un `SystemActor`.

---

## 24. Révocation par le propriétaire

Le `User` peut révoquer :

- sa session courante ;
- une autre de ses sessions ;
- une session reconnue comme appareil perdu ;
- une session persistante ancienne.

Condition recommandée :

    Session.UserId
    =
    ActorUserId

---

## 25. Révocation administrative

Un administrateur peut révoquer la session d’un autre `User` uniquement avec une permission dédiée.

Permission possible :

    identity.sessions.revoke

Pour une session privilégiée :

    identity.sessions.revoke-privileged

Pour une session d’impersonation :

    identity.sessions.revoke-impersonation

---

## 26. Révocation système

Un `SystemActor` peut révoquer une session à la suite :

- d’un rejeu de refresh token ;
- d’un changement de sécurité ;
- d’un verrouillage du compte ;
- d’une fraude détectée ;
- d’un signal externe ;
- d’une politique de risque ;
- d’une révocation du fournisseur d’identité ;
- d’une fermeture de workflow.

---

## 27. Sources de révocation

Valeurs recommandées pour `SessionRevocationSource` :

    UserSignOut
    UserSessionManagement
    AdministrativeAction
    SecurityIncident
    RefreshTokenReplay
    CredentialChange
    AccountRecovery
    UserSuspension
    UserLock
    UserDisablement
    UserRemoval
    IdentityProviderRevocation
    RiskEngine
    PolicyEnforcement
    ImpersonationTermination
    RecoveryWorkflowTermination
    ServiceCredentialRotation
    SystemCleanup
    Migration

---

## 28. UserSignOut

Le `User` se déconnecte volontairement de la session courante.

---

## 29. UserSessionManagement

Le `User` révoque une session depuis la liste de ses appareils ou connexions.

---

## 30. AdministrativeAction

Un acteur administratif autorisé termine la session.

---

## 31. SecurityIncident

La session est révoquée pour contenir un incident.

---

## 32. RefreshTokenReplay

Un ancien refresh credential a été rejoué.

La famille peut être considérée comme compromise.

---

## 33. CredentialChange

Une modification sensible des credentials exige la fin de certaines sessions.

---

## 34. AccountRecovery

La récupération de compte invalide une session potentiellement compromise.

---

## 35. UserSuspension

La suspension du `User` déclenche la révocation ou l’invalidation de ses sessions.

Pour toutes les sessions, utiliser normalement `RevokeAllUserSessions`.

---

## 36. IdentityProviderRevocation

Un fournisseur d’identité externe a signalé une révocation ou une déconnexion.

---

## 37. RiskEngine

Le moteur de risque a décidé qu’une session n’était plus acceptable.

---

## 38. Motifs de révocation

Valeurs recommandées pour `SessionRevocationReason` :

    UserRequestedSignOut
    UserRevokedDevice
    DeviceLost
    DeviceStolen
    SuspectedCompromise
    ConfirmedCompromise
    RefreshCredentialReplay
    PasswordChanged
    AuthenticationFactorChanged
    AccountRecovered
    UserSuspended
    UserLocked
    UserDisabled
    UserRemoved
    IdentityProviderSessionEnded
    PolicyViolation
    RiskThresholdExceeded
    ImpersonationEnded
    RecoveryCompleted
    ServiceCredentialRotated
    AdministrativeCorrection
    SecurityContainment
    Migration
    Other

---

## 39. Données d’entrée

### 39.1 Données obligatoires

| Donnée | Type | Description |
|---|---|---|
| `SessionId` | `SessionId` | Session à révoquer. |
| `RevokedBy` | `UserId` ou `SystemActor` | Acteur ou workflow responsable. |
| `RevokedAt` | Instant | Date métier de révocation. |
| `RevocationReason` | `SessionRevocationReason` | Motif de révocation. |
| `RevocationSource` | `SessionRevocationSource` | Origine de la révocation. |
| `RevokeSessionRequestId` | Identifiant | Identifiant idempotent. |

### 39.2 Données facultatives ou conditionnelles

| Donnée | Type | Description |
|---|---|---|
| `ExpectedUserId` | `UserId` | Propriétaire attendu de la session. |
| `ExpectedSessionVersion` | Version | Version attendue de la session. |
| `ExpectedSessionSecurityVersion` | Version | Version de sécurité attendue. |
| `ConfirmationId` | Identifiant | Confirmation éventuelle. |
| `SecurityIncidentId` | Identifiant | Incident de sécurité associé. |
| `RiskAssessmentId` | Identifiant | Évaluation de risque. |
| `IdentityProviderReference` | Identifiant | Référence du fournisseur externe. |
| `CaseReference` | Identifiant | Dossier administratif. |
| `CorrelationId` | Identifiant | Corrélation avec un workflow. |
| `Metadata` | Métadonnées contrôlées | Informations techniques limitées. |

---

## 40. Données calculées

La commande calcule notamment :

    PreviousStatus
    CurrentStatus
    RefreshTokenFamilyStatus
    ActiveElevationTerminated
    AccessCredentialInvalidationRequired
    SessionSecurityVersion
    SessionVersion

---

## 41. Préconditions

Avant exécution :

- la session existe ;
- la session n’est pas déjà révoquée par une autre intention ;
- l’acteur ou le workflow est autorisé ;
- le propriétaire attendu correspond lorsque fourni ;
- la source de révocation est valide ;
- le motif est cohérent avec la source ;
- l’instant de révocation est valide ;
- la version de la session correspond lorsque requise ;
- la version de sécurité correspond lorsque requise ;
- la demande est idempotente ;
- aucune modification concurrente incompatible n’a gagné.

---

## 42. Session active

Cas principal :

    Session.Status = Active
        ↓
    RevokeSession
        ↓
    Session.Status = Revoked

---

## 43. Session déjà révoquée

Deux cas doivent être distingués.

### Même RevokeSessionRequestId

Retourner le résultat initial.

### Nouvelle demande

Retourner :

    SessionAlreadyRevoked

La commande ne doit pas produire un second événement de révocation.

---

## 44. Session expirée

Une session expirée est déjà terminale.

Deux politiques sont possibles :

### Refus strict

    SessionAlreadyExpired

### Normalisation en succès sans changement

La commande retourne que la session était déjà terminale.

---

## 45. Recommandation pour une session expirée

Retourner :

    SessionAlreadyExpired

pour une nouvelle demande.

La révocation ne doit pas remplacer historiquement l’expiration.

---

## 46. Session introuvable

Pour une API publique de déconnexion, un succès opaque peut être souhaitable afin de ne pas révéler l’existence d’une session.

Au niveau du domaine, l’erreur reste :

    SessionNotFound

L’adaptation de sécurité de l’API peut masquer cette distinction.

---

## 47. Autorisation du propriétaire

Pour une révocation self-service :

    ActorUserId
    =
    Session.UserId

Si l’acteur tente de révoquer la session d’un autre utilisateur :

    SessionOwnershipMismatch

sauf autorisation administrative.

---

## 48. Révocation de la session courante

La session courante peut se révoquer elle-même.

Après commit, elle ne doit plus être utilisable pour une nouvelle action.

La réponse de révocation doit être construite avant toute réévaluation d’autorisation.

---

## 49. Révocation d’une autre session

Le `User` peut révoquer une autre session depuis une session active.

Le système doit vérifier :

    TargetSession.UserId
    =
    ActorSession.UserId

---

## 50. Session privilégiée

Une session privilégiée peut exiger :

- une authentification récente de l’acteur ;
- une permission administrative spécifique ;
- une confirmation ;
- une justification ;
- une notification de sécurité.

---

## 51. Session d’impersonation

Une session d’impersonation peut être révoquée par :

- l’acteur réel ;
- le sujet représenté selon la politique ;
- un administrateur ;
- le système ;
- la fin du workflow d’impersonation.

L’événement doit conserver :

    ActorUserId
    SubjectUserId

---

## 52. Session de récupération

Une session de récupération doit être révoquée à la fin du workflow.

Elle ne doit pas subsister après :

- changement de mot de passe ;
- remplacement des facteurs ;
- récupération terminée ;
- expiration du challenge.

---

## 53. Session de service

Une session technique peut être révoquée après :

- rotation de credential ;
- désactivation d’intégration ;
- incident ;
- suppression de l’identité technique ;
- changement de certificat.

---

## 54. Session fédérée

Une session fédérée peut être révoquée localement même si la session chez le fournisseur reste active.

Inversement, une révocation externe peut déclencher une révocation locale.

Les deux contextes doivent rester distincts.

---

## 55. Effet sur la famille de refresh tokens

Après révocation :

    RefreshTokenFamily.Status = Revoked

sauf si :

    RevocationSource = RefreshTokenReplay

Dans ce cas :

    RefreshTokenFamily.Status = Compromised

peut être plus précis.

---

## 56. Effet sur les refresh credentials descendants

Tous les credentials de la famille deviennent inutilisables, notamment :

    Active
    Used
    Rotated
    PendingIssuance

Aucun nouveau descendant ne doit pouvoir être émis.

---

## 57. Effet sur les credentials en cours d’émission

Si une émission de credential est en cours, elle doit être annulée ou rendue inutilisable.

Le système doit empêcher :

    Session revoked
    +
    credential issuance completes
    +
    usable credential returned

---

## 58. Effet sur SessionIssuanceState

Si la session possède :

    SessionIssuanceState

une révocation peut produire :

    Pending
        ↓
    Cancelled

ou :

    Issued
        ↓
    Revoked

---

## 59. SessionSecurityVersion

La commande doit incrémenter :

    Session.SessionSecurityVersion += 1

Cette version rend obsolètes :

- access tokens ;
- claims ;
- caches ;
- projections ;
- élévations dérivées ;
- décisions de sécurité antérieures.

---

## 60. Session.Version

La commande doit également incrémenter :

    Session.Version += 1

---

## 61. RevokedAt

Condition :

    RevokedAt >= Session.CreatedAt

et idéalement :

    RevokedAt <= CurrentTrustedTime + ClockTolerance

---

## 62. RevokedBy

Le domaine doit pouvoir distinguer :

    UserActor
    AdministrativeActor
    SystemActor
    ExternalIdentityProvider

La valeur doit être auditable.

---

## 63. Traitement métier

### 63.1 Vérifier l’idempotence

Le système recherche :

    SessionId + RevokeSessionRequestId

Une répétition identique retourne le résultat initial.

---

### 63.2 Charger la Session

Le système charge :

- `SessionId` ;
- `UserId` ;
- statut ;
- type ;
- dates ;
- contexte client ;
- contexte appareil ;
- refresh token family ;
- élévation ;
- acteur et sujet d’impersonation ;
- versions ;
- informations de révocation éventuelles.

---

### 63.3 Vérifier ExpectedUserId

Lorsque fourni :

    Session.UserId
    =
    ExpectedUserId

Sinon :

    SessionOwnershipMismatch

---

### 63.4 Vérifier le statut

Cas possibles :

    Active
    Revoked
    Expired

---

### 63.5 Traiter Active

La révocation peut poursuivre.

---

### 63.6 Traiter Revoked

Même demande :

    return original result

Autre demande :

    SessionAlreadyRevoked

---

### 63.7 Traiter Expired

Retourner :

    SessionAlreadyExpired

---

### 63.8 Charger le contexte de l’acteur

Pour un acteur humain :

- `ActorUserId` ;
- session de l’acteur ;
- permissions administratives ;
- niveau d’authentification ;
- relation avec la session cible ;
- éventuelle self-revocation.

Pour un `SystemActor` :

- identité technique ;
- autorité ;
- source ;
- incident ou politique associée.

---

### 63.9 Autoriser la révocation

Le système vérifie l’un des cas suivants :

    Actor owns Session

ou :

    Actor has administrative revocation authority

ou :

    SystemActor is authorized by policy

---

### 63.10 Vérifier la cohérence source / motif

Exemples :

    RevocationSource = RefreshTokenReplay
    requires
    RevocationReason = RefreshCredentialReplay
    or ConfirmedCompromise

    RevocationSource = UserSignOut
    requires
    user-initiated reason

---

### 63.11 Vérifier la confirmation

Une confirmation peut être requise pour révoquer :

- une autre session ;
- une session privilégiée ;
- une session d’impersonation ;
- une session technique critique.

---

### 63.12 Vérifier ExpectedSessionVersion

Condition :

    Session.Version
    =
    ExpectedSessionVersion

---

### 63.13 Vérifier ExpectedSessionSecurityVersion

Condition :

    Session.SessionSecurityVersion
    =
    ExpectedSessionSecurityVersion

---

### 63.14 Calculer l’impact

Le système détermine :

    RefreshTokenFamilyWillBeRevoked
    ActiveElevationWillBeTerminated
    AccessCredentialInvalidationRequired
    ImpersonationWillEnd
    RecoveryWorkflowWillEnd
    ActiveIntegrationMayBeInterrupted

---

### 63.15 Modifier le statut

    Session.Status = Revoked

---

### 63.16 Enregistrer la révocation

La session peut conserver :

    RevokedAt
    RevokedBy
    RevocationReason
    RevocationSource

---

### 63.17 Terminer l’élévation

Si une élévation est active :

    Session.Elevation.Status = Revoked

ou :

    Session.Elevation.TerminatedAt = RevokedAt

selon le modèle.

---

### 63.18 Invalider la famille de refresh tokens

    RefreshTokenFamily.Status = Revoked

ou :

    Compromised

selon le motif.

---

### 63.19 Empêcher toute nouvelle rotation

Le credential courant ne peut plus produire de descendant.

---

### 63.20 Incrémenter SessionSecurityVersion

    Session.SessionSecurityVersion += 1

---

### 63.21 Incrémenter Session.Version

    Session.Version += 1

---

### 63.22 Produire SessionRevoked

L’agrégat produit :

    SessionRevoked

---

### 63.23 Enregistrer l’idempotence

Le résultat est associé à :

    RevokeSessionRequestId

---

### 63.24 Commit atomique

Le même commit logique doit contenir :

    Session.Status = Revoked
    +
    revocation metadata
    +
    active elevation terminated
    +
    refresh token family invalidated
    +
    SessionSecurityVersion incremented
    +
    Session.Version incremented
    +
    idempotency record
    +
    SessionRevoked event

---

## 64. Résultat attendu

Après succès :

    Session
    ├── same SessionId
    ├── same UserId
    ├── Status: Revoked
    ├── same SessionType
    ├── same CreatedAt
    ├── same AuthenticatedAt
    ├── RevokedAt: set
    ├── RevokedBy: set
    ├── RevocationReason: set
    ├── RevocationSource: set
    ├── Elevation: ineffective or Revoked
    ├── RefreshTokenFamily: Revoked or Compromised
    ├── SessionSecurityVersion: incremented
    └── Session.Version: incremented

---

## 65. Résultat fonctionnel

Structure recommandée :

    RevokeSessionResult
    ├── SessionId
    ├── UserId
    ├── PreviousStatus
    ├── CurrentStatus
    ├── RevokedAt
    ├── RevocationReason
    ├── RevocationSource
    ├── SelfRevocation
    ├── ActiveElevationTerminated
    ├── RefreshTokenFamilyInvalidated
    ├── AccessCredentialInvalidationRequired
    ├── ImpersonationTerminated
    ├── RecoverySessionTerminated
    ├── SessionSecurityVersion
    └── SessionVersion

---

## 66. Invariants concernés

### État terminal

    Session.Status = Revoked
    is terminal

---

### Identité stable

    SessionId remains unchanged

---

### Propriétaire stable

    UserId remains unchanged

---

### Type stable

    SessionType remains unchanged

---

### Refresh interdit

    Revoked Session
    cannot be refreshed

---

### Élévation interdite

    Revoked Session
    cannot be elevated

---

### Autorisation interdite

    Revoked Session
    cannot authorize requests

---

### Famille de refresh invalidée

    Session revoked
    implies
    RefreshTokenFamily not Active

---

### Élévation inefficace

    Session revoked
    implies
    active elevation ineffective

---

### Aucun secret dans l’événement

Les credentials bruts restent hors du domaine événementiel.

---

### Version de sécurité modifiée

    SessionSecurityVersion changes
    after revocation

---

## 67. Événement produit

### SessionRevoked

Contenu recommandé :

- `SessionId`
- `UserId`
- `ActorUserId`
- `SubjectUserId`
- `SessionType`
- `PreviousStatus`
- `CurrentStatus`
- `CreatedAt`
- `AuthenticatedAt`
- `RevokedAt`
- `RevokedBy`
- `RevocationReason`
- `RevocationSource`
- `SelfRevocation`
- `ActiveElevationTerminated`
- `ElevationVersion`
- `RefreshTokenFamilyId`
- `RefreshTokenFamilyFinalStatus`
- `AccessCredentialInvalidationRequired`
- `ImpersonationTerminated`
- `RecoverySessionTerminated`
- `DeviceId`
- `DeviceType`
- `ClientApplicationId`
- `ClientType`
- `SecurityIncidentId`
- `RiskAssessmentId`
- `IdentityProviderReference`
- `CaseReference`
- `SessionSecurityVersion`
- `SessionVersion`
- `RevokeSessionRequestId`
- `CorrelationId`

---

## 68. Données interdites dans SessionRevoked

L’événement ne doit pas contenir :

- access token ;
- refresh token ;
- cookie ;
- mot de passe ;
- secret TOTP ;
- code OTP ;
- recovery code ;
- clé privée ;
- credential WebAuthn brut ;
- assertion biométrique brute ;
- contenu complet d’un fournisseur d’identité ;
- adresse IP complète sans politique explicite ;
- données personnelles inutiles.

---

## 69. Événements secondaires possibles

Après commit :

    SessionAccessCredentialInvalidationRequested
    SessionRefreshTokenFamilyRevoked
    SessionElevationTerminated
    SessionSecurityProjectionUpdated
    UserSessionIndexUpdateRequested
    SessionRevocationNotificationRequested
    ImpersonationEnded
    RecoverySessionEnded
    ServiceIntegrationInterruptionRequested

---

## 70. SessionRevocationRejected

Une tentative refusée peut produire un événement de sécurité lorsque pertinent.

Exemples :

- tentative de révoquer la session d’un autre utilisateur ;
- absence de permission administrative ;
- incohérence de propriétaire ;
- tentative avec contexte compromis ;
- conflit de version suspect.

Contenu recommandé :

- `SessionId`
- `ExpectedUserId`
- `ActorUserId`
- `RejectedAt`
- `RejectionReason`
- `RiskAssessmentId`
- `ClientApplicationId`
- `CorrelationId`

---

## 71. Événements non produits

La commande ne produit pas :

    UserSuspended
    UserLocked
    UserRemoved
    MembershipSuspended
    MembershipRemoved
    RoleDisabled
    PermissionRevoked
    SessionExpired

---

## 72. Effet sur les autres Sessions

Aucun effet direct.

Les autres sessions du même `User` restent actives.

Pour les révoquer toutes, utiliser :

    RevokeAllUserSessions

---

## 73. Effet sur la Session de l’acteur

Si l’acteur révoque sa session courante :

    ActorSessionId = TargetSessionId

alors la session de l’acteur devient immédiatement terminale.

---

## 74. Réponse après self-revocation

La réponse métier doit être construite et retournée sans exiger une nouvelle autorisation de la session révoquée.

L’adaptateur HTTP peut ensuite :

- supprimer le cookie ;
- effacer le credential local ;
- retourner un succès ;
- rediriger vers la connexion.

---

## 75. Suppression des cookies

La suppression d’un cookie est un effet d’interface ou d’infrastructure.

Le domaine produit seulement une information telle que :

    ClientCredentialCleanupRequired = true

---

## 76. Révocation distante

Lorsqu’un `User` révoque une autre session :

- la session courante reste active ;
- la session cible devient terminale ;
- l’appareil cible peut recevoir une notification ;
- les credentials locaux de l’appareil cible seront refusés à la prochaine utilisation.

---

## 77. Révocation d’une session déjà compromise

La commande doit pouvoir réussir même si certains credentials sont introuvables ou déjà invalidés.

La session reste la source métier principale.

---

## 78. Idempotence

Clé recommandée :

    SessionId + RevokeSessionRequestId

---

## 79. Empreinte idempotente

L’empreinte doit inclure au minimum :

    SessionId
    RevokedBy
    RevokedAt
    RevocationReason
    RevocationSource
    ExpectedUserId
    SecurityIncidentId
    RiskAssessmentId
    IdentityProviderReference
    CaseReference

---

## 80. Répétition identique

Une répétition exacte retourne le résultat initial sans :

- produire un second événement ;
- modifier `RevokedAt` ;
- remplacer le motif initial ;
- incrémenter une nouvelle version ;
- révoquer une seconde fois la famille ;
- envoyer une seconde notification ;
- répéter l’invalidation des access tokens.

---

## 81. Nouvelle demande sur une Session déjà révoquée

Avec un autre identifiant :

    SessionAlreadyRevoked

La seconde demande ne remplace pas la cause historique initiale.

---

## 82. Conflit d’idempotence

Le même `RevokeSessionRequestId` avec une autre intention produit :

    IdempotencyConflict

---

## 83. Reprise après réponse perdue

Cas :

    RevokeSession succeeds
        ↓
    transaction commits
        ↓
    response is lost
        ↓
    caller retries

Le retry retourne :

- le même statut ;
- le même `RevokedAt` ;
- le même motif ;
- la même source ;
- les mêmes versions ;
- le même résultat logique.

---

## 84. Concurrence

### 84.1 Deux révocations concurrentes

Une seule commande gagne.

La seconde rencontre :

    SessionVersionConflict

ou, après rechargement :

    SessionAlreadyRevoked

---

### 84.2 Revoke contre RefreshSession

Si la révocation gagne :

    RefreshSession
    → SessionRevoked

Si le refresh gagne avant la révocation :

- le nouveau credential est créé ;
- la révocation suivante invalide la session et toute la famille.

L’état final reste `Revoked`.

---

### 84.3 Revoke contre ElevateSession

Si la révocation gagne :

    ElevateSession
    → SessionRevoked

Si l’élévation gagne d’abord :

- l’élévation est créée ;
- la révocation suivante la termine immédiatement.

---

### 84.4 Revoke contre ExpireSession

Une seule transition terminale doit gagner.

Si l’expiration gagne :

    SessionAlreadyExpired

Si la révocation gagne :

    SessionAlreadyRevoked

L’événement final doit correspondre à la transition réellement commitée.

---

### 84.5 Revoke contre RevokeAllUserSessions

Les deux opérations sont compatibles sur l’intention, mais une seule transition de session doit être persistée.

L’autre opération doit considérer la session comme déjà terminale.

---

### 84.6 Revoke contre changement de mot de passe

Les deux peuvent invalider la session.

La révocation explicite reste auditable.

---

### 84.7 Revoke contre émission de credential

La révocation doit empêcher qu’un credential nouvellement émis reste utilisable.

Une version ou un verrou de sécurité doit protéger cette course.

---

### 84.8 Revoke contre consommation d’élévation

Une action sensible ne doit pas pouvoir consommer une élévation après la révocation.

La décision doit vérifier la session au moment du commit de l’action.

---

## 85. Atomicité

Le commit logique doit contenir :

    Session.Status = Revoked
    +
    revocation metadata
    +
    active elevation terminated
    +
    refresh token family invalidated
    +
    SessionSecurityVersion incremented
    +
    Session.Version incremented
    +
    idempotency record
    +
    SessionRevoked event

---

## 86. Coordination avec le stockage de credentials

Si les refresh credentials sont stockés dans un composant séparé, le système doit garantir qu’aucun refresh ne réussit après la révocation.

Stratégies :

- transaction partagée ;
- vérification systématique du statut de session ;
- version de sécurité ;
- verrou par `SessionId` ;
- révocation de famille via événement avec refus basé sur la session ;
- stockage fortement cohérent.

---

## 87. Source de vérité recommandée

Même si la famille de refresh n’a pas encore été mise à jour :

    Session.Status = Revoked

doit suffire à refuser :

    RefreshSession

Ainsi, la sécurité ne dépend pas uniquement d’un handler asynchrone.

---

## 88. Outbox transactionnelle

`SessionRevoked` doit être enregistré dans la même transaction que le changement de statut.

Sa publication intervient après commit.

---

## 89. États interdits

    Revoked Session
    → Active

    Revoked Session
    → refreshed

    Revoked Session
    → elevated

    Revoked Session
    → authorizes request

    Session.Status = Revoked
    AND
    RefreshTokenFamily.Status = Active

    Session.Status = Revoked
    AND
    active elevation considered valid

    Session revoked
    AND
    SessionSecurityVersion unchanged

    SessionRevoked event persisted
    AND
    Session.Status remains Active

    Session.Status = Revoked
    AND
    SessionRevoked event missing

    RevokeSession changes UserId

    RevokeSession changes SessionType

    RevokeSession removes Membership

    RevokeSession modifies Role

    RevokeSession revokes Permission

    raw credential appears in event

---

## 90. Effets externes

Après succès, des handlers peuvent :

- invalider les access tokens ;
- révoquer la famille de refresh credentials ;
- supprimer ou marquer les credentials techniques ;
- mettre à jour l’index des sessions du `User` ;
- actualiser les projections de sécurité ;
- supprimer les cookies côté client ;
- notifier le propriétaire ;
- clôturer une impersonation ;
- clôturer une récupération ;
- interrompre une intégration ;
- créer ou mettre à jour un incident de sécurité ;
- consigner la révocation auprès d’un fournisseur externe.

---

## 91. Notifications

Une notification peut être envoyée lorsque :

- le `User` révoque une session distante ;
- une session est révoquée pour compromission ;
- un appareil perdu ou volé est concerné ;
- une session privilégiée est révoquée ;
- une session d’impersonation est interrompue ;
- un administrateur révoque la session ;
- un fournisseur externe déclenche la révocation.

Une déconnexion ordinaire de la session courante ne nécessite généralement pas de notification.

---

## 92. Notification de sécurité

La notification peut indiquer :

- le type d’appareil ;
- l’application ;
- une date approximative ;
- le motif général ;
- l’action à entreprendre si la révocation n’était pas attendue.

Elle ne doit pas révéler :

- le token ;
- l’adresse IP complète sans nécessité ;
- des détails internes d’incident ;
- des secrets ;
- des données sur d’autres utilisateurs.

---

## 93. Audit

Une révocation réussie doit permettre de connaître :

- la session concernée ;
- son propriétaire ;
- l’acteur réel ;
- le sujet représenté ;
- le type de session ;
- le statut précédent ;
- le statut final ;
- la date de création ;
- la date de dernière authentification ;
- la date de révocation ;
- la source ;
- le motif ;
- la self-revocation éventuelle ;
- l’élévation terminée ;
- la famille de refresh invalidée ;
- l’impersonation terminée ;
- la récupération terminée ;
- l’incident ou le risque associé ;
- l’appareil ;
- le client ;
- les versions de sécurité ;
- la demande idempotente ;
- le workflow corrélé.

---

## 94. Questions auxquelles l’audit doit répondre

    which Session was revoked
    which User owned it
    who initiated the revocation
    whether the User revoked their own Session
    why the Session was revoked
    which source triggered the revocation
    when the Session was revoked
    whether an active elevation was terminated
    whether refresh credentials were invalidated
    whether impersonation was active
    whether a recovery workflow was active
    whether a security incident was involved
    which Session security version became effective

---

## 95. Confidentialité

Les données suivantes doivent être minimisées :

- adresse IP ;
- user agent ;
- appareil ;
- localisation ;
- fournisseur d’identité ;
- contexte de risque ;
- détail d’incident.

Aucun secret ne doit être conservé dans l’événement ou l’audit.

---

## 96. Sécurité

La commande doit garantir que :

- seule une autorité valide révoque la session ;
- le propriétaire peut gérer ses propres sessions ;
- une autorité administrative est requise pour les sessions tierces ;
- une session révoquée ne redevient jamais active ;
- un refresh est impossible après révocation ;
- une élévation est impossible après révocation ;
- les refresh credentials deviennent inutilisables ;
- l’élévation active devient inefficace ;
- les access tokens peuvent être invalidés ou expirer rapidement ;
- les versions de sécurité sont incrémentées ;
- les courses avec refresh, elevation et credential issuance sont protégées ;
- aucun secret n’apparaît dans les événements ;
- l’opération est idempotente ;
- la révocation et l’événement sont persistés atomiquement.

---

## 97. Erreurs métier

### SessionNotFound

La session n’existe pas.

---

### SessionAlreadyRevoked

La session est déjà révoquée par une autre demande.

---

### SessionAlreadyExpired

La session est déjà expirée.

---

### SessionNotActive

La session n’est pas active.

---

### SessionOwnershipMismatch

La session n’appartient pas au `User` attendu.

---

### ActorNotAuthorized

L’acteur ne peut pas révoquer de session.

---

### SessionRevocationNotAuthorized

L’acteur ne peut pas révoquer cette session précise.

---

### AdministrativeSessionRevocationPermissionRequired

Une permission administrative spécifique est nécessaire.

---

### PrivilegedSessionRevocationNotAuthorized

L’acteur ne peut pas révoquer une session privilégiée.

---

### ImpersonationSessionRevocationNotAuthorized

L’acteur ne peut pas révoquer cette session d’impersonation.

---

### ServiceSessionRevocationNotAuthorized

L’acteur ne peut pas révoquer cette session technique.

---

### ConfirmationRequired

Une confirmation est nécessaire.

---

### ConfirmationInvalid

La confirmation ne correspond pas à l’intention.

---

### RevocationReasonRequired

Un motif est obligatoire.

---

### RevocationSourceInvalid

La source de révocation est invalide.

---

### RevocationReasonSourceMismatch

Le motif n’est pas cohérent avec la source.

---

### SecurityIncidentReferenceRequired

Un incident de sécurité doit être référencé.

---

### RiskAssessmentRequired

Une évaluation de risque est nécessaire.

---

### IdentityProviderReferenceRequired

Une référence externe est nécessaire.

---

### SessionVersionConflict

La session a changé depuis la lecture initiale.

---

### SessionSecurityVersionConflict

L’état de sécurité de la session a changé.

---

### RefreshTokenFamilyRevocationConflict

La famille de refresh a changé de manière concurrente.

---

### SessionRevocationConflict

Une opération concurrente empêche la révocation.

---

### IdempotencyConflict

Le même identifiant représente une autre intention.

---

## 98. Décisions de conception

### RevokeSession cible une seule Session

Les autres sessions du `User` restent inchangées.

---

### Revoked est un état terminal

Une nouvelle authentification crée une nouvelle session.

---

### La révocation ne suspend pas le User

Le compte reste inchangé.

---

### La révocation ne modifie pas les Memberships

Les accès métier du `User` restent définis par ses memberships.

---

### La révocation ne modifie pas les Roles

Les responsabilités du `User` ne changent pas.

---

### La révocation ne modifie pas les Permissions

Seul le contexte d’authentification devient invalide.

---

### Les refresh credentials sont invalidés

Aucun renouvellement n’est possible.

---

### L’élévation active devient inefficace

Une session terminale ne peut conserver une assurance élevée utilisable.

---

### SessionSecurityVersion est incrémentée

Les tokens et caches obsolètes peuvent être détectés.

---

### La session reste conservée

Elle sert à l’audit et à l’historique.

---

### La cause initiale n’est pas remplacée

Une seconde demande ne modifie pas le motif de révocation historique.

---

### La session courante peut se révoquer elle-même

La réponse doit être remise sans nouvelle autorisation.

---

### Session.Status est la source de vérité

Même si l’invalidation des credentials est asynchrone, une session révoquée doit être refusée.

---

### Un événement métier dédié est produit

    SessionRevoked

---

## 99. Cas limites

### Révocation de la session courante

La commande réussit.

Le client doit supprimer ses credentials locaux.

---

### Révocation d’une autre session du même User

La session de l’acteur reste active.

La session cible devient terminale.

---

### Session sans refresh credential

La révocation réussit.

L’absence de refresh token ne bloque pas la transition.

---

### Session avec credential en cours de rotation

La concurrence doit garantir qu’aucun nouveau credential utilisable ne survive à la révocation.

---

### Session avec élévation active

L’élévation devient immédiatement inefficace.

---

### Session avec élévation expirée

La révocation réussit sans événement supplémentaire obligatoire pour l’élévation.

---

### Session fédérée

La révocation locale peut réussir même si le fournisseur externe est indisponible.

La déconnexion externe peut être tentée après commit.

---

### Session d’impersonation

La révocation termine l’impersonation et conserve les identités de l’acteur et du sujet dans l’audit.

---

### Session de récupération

La révocation empêche toute poursuite du workflow de récupération.

---

### Session de service

La révocation peut interrompre une intégration.

Une notification opérationnelle peut être nécessaire.

---

### Session déjà expirée techniquement mais non matérialisée

Le système doit évaluer l’expiration temporelle avant d’appliquer la révocation.

Si :

    CurrentTime >= ExpiresAt

la politique doit déterminer si la transition matérialisée est `Expired` ou si la révocation explicite gagne.

Recommandation :

    temporal expiration is evaluated first
    unless an earlier explicit revocation was already committed

---

### Token autonome encore valide quelques secondes

La révocation métier est effective.

Une courte fenêtre technique peut subsister si l’architecture n’utilise pas d’introspection.

Cette limite doit être connue et bornée.

---

### Session introuvable lors d’une déconnexion publique

L’adaptateur peut retourner un succès opaque.

Le domaine conserve `SessionNotFound`.

---

### Retry après succès

Le résultat initial est retourné sans second événement.

---

## 100. Checklist de validation

Avant commit :

    Session exists
    Session ownership matches when expected
    Session status is evaluated
    Session is not already terminal under another cause
    Actor or SystemActor is authorized
    RevocationSource is valid
    RevocationReason is valid
    RevocationReason matches source
    Required confirmation is valid
    Required security incident reference is valid
    Required risk assessment is valid
    ExpectedSessionVersion matches
    ExpectedSessionSecurityVersion matches
    Idempotency is verified
    Active elevation impact is calculated
    Refresh token family impact is calculated
    Access credential invalidation requirement is calculated
    Impersonation impact is calculated
    Recovery workflow impact is calculated
    Session can transition to Revoked
    Refresh token family can be invalidated
    SessionSecurityVersion can be incremented
    Session.Version can be incremented
    No User state is modified
    No Membership is modified
    No Role is modified
    No Permission is modified
    No raw credential enters the event
    SessionRevoked can be persisted atomically

---

## 101. Synthèse

`RevokeSession` met fin explicitement et irréversiblement à une session existante.

Elle garantit que :

- la session existe ;
- l’acteur ou le workflow est autorisé ;
- le propriétaire peut révoquer ses propres sessions ;
- une autorité spécifique est requise pour révoquer la session d’un autre utilisateur ;
- la session passe de `Active` à `Revoked` ;
- `SessionId` reste inchangé ;
- `UserId` reste inchangé ;
- `SessionType` reste inchangé ;
- la session ne peut plus être utilisée ;
- la session ne peut plus être rafraîchie ;
- la session ne peut plus être élevée ;
- les refresh credentials deviennent inutilisables ;
- la famille de refresh tokens est révoquée ou compromise ;
- l’élévation active devient immédiatement inefficace ;
- les access tokens peuvent être invalidés ou rendus obsolètes ;
- `SessionSecurityVersion` est incrémentée ;
- les memberships ne sont pas modifiés ;
- les rôles ne sont pas modifiés ;
- les permissions ne sont pas modifiées ;
- les autres sessions du `User` restent actives ;
- la cause de révocation reste auditable ;
- une seconde demande ne remplace pas l’historique initial ;
- l’opération est idempotente ;
- les conflits avec refresh, elevation et expiration sont protégés ;
- aucun secret n’entre dans les événements ;
- la révocation et l’événement sont persistés atomiquement.

Le résultat conceptuel est :

    Session
    ├── same SessionId
    ├── same UserId
    ├── Status: Revoked
    ├── terminal lifecycle state
    ├── refresh disabled
    ├── elevation ineffective
    ├── RefreshTokenFamily invalidated
    ├── access credentials invalidated or obsolete
    ├── SessionSecurityVersion incremented
    ├── historical context preserved
    └── no impact on other User Sessions