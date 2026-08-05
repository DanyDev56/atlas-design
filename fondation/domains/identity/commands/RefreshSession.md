---
id: IDN-CMD-REFRESH-SESSION
title: RefreshSession
status: Draft
owner: Product
version: 1.0.0
last_updated: 2026-07-31

aggregate: Session

references:
  - README.md
  - ../entities.md
  - ../aggregates.md
  - ../relationships.md
  - ../value-objects.md
  - ../invariants.md
  - ../permissions.md
  - ../events/SessionRefreshed.md
  - ../events/SessionRefreshRejected.md
  - CreateSession.md
  - RevokeSession.md
  - RevokeAllUserSessions.md
  - ExpireSession.md
  - ../workflows.md
  - ../decision-record.md
---

# RefreshSession

## 1. Objectif

La commande `RefreshSession` renouvelle la continuité d'une `Session` existante à partir d'un refresh credential valide.

Elle permet à un client de continuer à utiliser une session sans demander à nouveau une authentification interactive complète.

    valid refresh credential
            ↓
    RefreshSession
            ↓
    new session credentials

La commande garantit que :

- la session existe ;
- la session est active ;
- le refresh credential appartient à cette session ;
- le credential n'est pas expiré ;
- le credential n'est pas révoqué ;
- le credential n'a pas déjà été utilisé ;
- la version de sécurité du `User` reste valide ;
- la politique de refresh autorise le renouvellement ;
- la rotation du credential est effectuée ;
- un rejeu d'un ancien credential est détectable ;
- la session ne change pas de `User` ;
- la session ne change pas de `SessionType` ;
- aucune permission n'est accordée ou figée par la commande.

---

## 2. Intention métier

La commande répond à l'intention :

    continue an existing authenticated Session
    without requiring a new interactive authentication

Elle ne signifie pas :

    authenticate a User from scratch

et ne doit pas être utilisée comme alternative à `CreateSession`.

---

## 3. Distinction avec CreateSession

`CreateSession` :

    AuthenticationProof
            ↓
    new Session

`RefreshSession` :

    existing Session
    +
    valid RefreshCredential
            ↓
    continued Session

`RefreshSession` ne crée donc pas de nouveau `SessionId`.

---

## 4. SessionId inchangé

Après un refresh :

    SessionId_before
    =
    SessionId_after

Le refresh renouvelle les credentials techniques associés à la session.

Il ne crée pas une nouvelle identité de session.

---

## 5. Credential rotation

Chaque refresh réussi effectue une rotation du refresh credential :

    RefreshCredential N
            ↓
    RefreshSession
            ↓
    RefreshCredential N+1

Le credential précédent devient inutilisable.

---

## 6. Pourquoi la rotation est obligatoire

Sans rotation :

    stolen refresh credential
    +
    long lifetime
    =
    persistent session takeover

Avec rotation :

    credential N
            ↓
        used once
            ↓
    credential N invalidated
            ↓
    credential N+1 issued

Un rejeu de `N` peut alors être détecté.

---

## 7. Refresh Token Family

Les credentials d'une même session appartiennent à une famille :

    RefreshTokenFamilyId

Exemple :

    Family A
    ├── Credential 1
    ├── Credential 2
    ├── Credential 3
    └── Credential 4

Chaque rotation remplace le credential actif par un nouveau credential de la même famille.

---

## 8. Credential lineage

Les credentials peuvent conserver une relation logique :

    Credential 1
            ↓
    Credential 2
            ↓
    Credential 3

Cette relation permet de détecter :

    Credential 1 reused

après l'émission de `Credential 2`.

---

## 9. Rejeu détecté

Un rejeu d'un ancien refresh credential doit être traité comme un événement de sécurité.

Exemple :

    Credential 3
            ↓
    refresh succeeds
            ↓
    Credential 4 issued

Puis :

    Credential 3 reused

Le système doit considérer que :

    RefreshTokenFamily
    may be compromised

La politique recommandée est :

    revoke refresh token family
    +
    revoke current Session
    +
    invalidate descendants

---

## 10. Agrégat concerné

    Session

La commande modifie uniquement l'état de la `Session`.

Elle peut consulter :

- `User` ;
- état de sécurité du `User` ;
- version de sécurité ;
- politique de session ;
- registre des refresh credentials ;
- contexte du device ;
- contexte client ;
- politiques de risque.

Elle ne modifie pas directement :

- `User` ;
- `Membership` ;
- `Role` ;
- `Permission`.

---

## 11. User

Le `User` reste inchangé.

La commande vérifie notamment :

    User.Status
    User.SecurityVersion
    User.AuthenticationStateVersion

---

## 12. Authorization

Le refresh ne recalcule pas les permissions comme état de session.

La chaîne reste :

    Session
    → User
    → Membership
    → Role
    → Permission

Un refresh ne doit donc pas :

- ajouter un rôle ;
- retirer un rôle ;
- changer un membership ;
- copier des permissions dans la session ;
- contourner une révocation d'accès.

---

## 13. Workspace

La session reste globale au `User`.

Le refresh ne sélectionne pas de nouveau workspace.

Si une requête ultérieure utilise :

    WorkspaceId

l'autorisation est réévaluée normalement.

---

## 14. SessionStatus

Valeurs :

    Active
    Revoked
    Expired

Seule une session :

    Active

peut être rafraîchie.

---

## 15. RefreshCredentialStatus

Le credential peut être modélisé séparément :

    Active
    Used
    Revoked
    Expired
    Compromised

Un credential `Used` ne peut jamais être réutilisé.

---

## 16. RefreshTokenFamilyStatus

Valeurs recommandées :

    Active
    Revoked
    Compromised
    Expired

Une famille `Compromised` ne peut plus produire de nouveau credential.

---

## 17. Données d'entrée

### 17.1 Données obligatoires

| Donnée | Type | Description |
|---|---|---|
| `SessionId` | `SessionId` | Session concernée. |
| `RefreshCredentialId` | Identifiant | Référence interne du credential présenté. |
| `RefreshCredentialProof` | Secret protégé | Preuve cryptographique du credential. |
| `RefreshRequestId` | Identifiant | Clé d'idempotence. |
| `RequestedAt` | Instant | Instant de la demande. |

### 17.2 Données facultatives

| Donnée | Type | Description |
|---|---|---|
| `DeviceId` | `DeviceId` | Appareil attendu. |
| `ClientApplicationId` | Identifiant | Application cliente. |
| `ClientType` | Enum | Web, mobile, desktop, API. |
| `IpAddress` | Valeur protégée | Adresse observée. |
| `UserAgent` | Texte contrôlé | Agent client. |
| `RiskAssessmentId` | Identifiant | Évaluation de risque. |
| `CorrelationId` | Identifiant | Corrélation du workflow. |
| `Metadata` | Métadonnées contrôlées | Informations techniques limitées. |

---

## 18. Secret du refresh credential

Le secret brut :

    RefreshCredentialProof

ne doit pas être :

- stocké dans `Session` ;
- écrit dans un event ;
- journalisé ;
- envoyé à l'audit ;
- persisté en clair.

Le composant de sécurité peut utiliser :

    hash
    +
    salt
    +
    pepper

ou une autre stratégie adaptée.

---

## 19. RefreshCredentialId

Il faut distinguer :

    RefreshCredentialId

de :

    RefreshCredentialProof

Le premier identifie le credential.

Le second prouve sa possession.

---

## 20. RefreshTokenFamilyId

Une famille doit être stable pendant toute la durée de la session.

    Session
    → RefreshTokenFamily

Une nouvelle session créée par `CreateSession` possède une nouvelle famille.

---

## 21. Rotation

Lors d'un refresh réussi :

    old credential
            ↓
        Used

puis :

    new credential
            ↓
        Active

Le nouveau credential doit posséder :

    CredentialId
    RefreshTokenFamilyId
    IssuedAt
    ExpiresAt
    ParentCredentialId
    CredentialVersion

---

## 22. Credential version

La version permet d'éviter les doubles consommations :

    ExpectedCredentialVersion

Le changement doit être atomique.

---

## 23. Conditions préalables

Avant exécution :

- `Session` existe ;
- `Session.Status = Active` ;
- `User` existe ;
- `User.Status` autorise encore la session ;
- `User.SecurityVersion` correspond ;
- `User.AuthenticationStateVersion` correspond ;
- `RefreshTokenFamily` existe ;
- `RefreshTokenFamily.Status = Active` ;
- `RefreshCredential` existe ;
- le credential appartient à la session ;
- le credential appartient à la famille attendue ;
- le credential est actif ;
- le credential n'est pas expiré ;
- le credential n'est pas révoqué ;
- le credential n'a jamais été consommé ;
- le credential est cryptographiquement valide ;
- le credential correspond au client attendu lorsque cette contrainte est activée ;
- le credential correspond à l'appareil attendu lorsque cette contrainte est activée ;
- la politique de refresh autorise le renouvellement ;
- la durée maximale de la session n'est pas dépassée ;
- le refresh n'est pas interdit par un changement de sécurité ;
- aucune révocation globale n'a gagné ;
- la demande est idempotente ;
- aucune opération concurrente incompatible n'a gagné.

---

## 24. UserStatus

Une session peut uniquement être rafraîchie si le statut du `User` le permet.

Recommandation :

    Active
    → refresh allowed

    Pending
    → refresh only for restricted activation Session

    Suspended
    → refresh forbidden

    Locked
    → refresh forbidden

    Disabled
    → refresh forbidden

    Removed
    → refresh forbidden

---

## 25. SecurityVersion

La session capture :

    UserSecurityVersion

au moment de sa création.

Lors d'un refresh :

    Session.UserSecurityVersion
    =
    User.SecurityVersion

doit rester vrai.

Sinon :

    UserSecurityVersionConflict

---

## 26. Événements provoquant une invalidation

Une augmentation de `User.SecurityVersion` peut notamment intervenir après :

- changement de mot de passe ;
- compromission de compte ;
- récupération de compte ;
- révocation globale ;
- changement critique des facteurs d'authentification ;
- désactivation du compte ;
- changement de politique de sécurité ;
- incident de sécurité.

Le refresh doit respecter cette invalidation.

---

## 27. AuthenticationStateVersion

Une seconde version peut être utilisée pour les changements liés à l'authentification :

    AuthenticationStateVersion

Elle peut être invalidée sans nécessairement modifier d'autres informations du `User`.

---

## 28. SessionSecurityVersion

La session peut également posséder :

    SessionSecurityVersion

Cette version évolue lors des opérations sensibles sur la session.

---

## 29. RefreshPolicy

Le renouvellement doit être contrôlé par :

    RefreshSessionPolicy

Structure recommandée :

    RefreshSessionPolicy
    ├── RefreshAllowed
    ├── AbsoluteLifetime
    ├── RefreshCredentialLifetime
    ├── IdleLifetime
    ├── MaximumRefreshCount
    ├── MaximumAuthenticationAge
    ├── RotationRequired
    ├── ReuseDetection
    ├── FamilyRevocationOnReuse
    ├── DeviceBinding
    ├── ClientBinding
    ├── RiskReassessment
    └── GracePeriod

---

## 30. Absolute lifetime

Le refresh ne doit jamais permettre une session infinie.

Exemple :

    Session created at T0
    AbsoluteLifetime = 30 days

    T0 + 29 days
    → refresh allowed

    T0 + 31 days
    → refresh forbidden

Le refresh peut prolonger :

    credential expiration

mais pas nécessairement :

    session absolute expiration

---

## 31. Idle lifetime

Une session peut également avoir :

    IdleExpiresAt

Un refresh peut renouveler la période d'inactivité si la politique l'autorise.

---

## 32. Maximum refresh count

Une politique peut limiter le nombre de rotations.

Exemple :

    MaximumRefreshCount = 1000

Une limite peut être utile pour :

- sessions persistantes ;
- clients suspects ;
- politiques de sécurité particulières.

Elle ne doit toutefois pas remplacer l'expiration absolue.

---

## 33. Device binding

Le credential peut être lié à :

    DeviceId

Si le binding est activé :

    PresentedDeviceId
    =
    Credential.DeviceId

Sinon :

    DeviceBindingMismatch

---

## 34. Client binding

Le credential peut également être lié à :

    ClientApplicationId

ou :

    ClientType

La politique doit définir si le changement de client est autorisé.

---

## 35. Web session

Pour une application web, le refresh doit idéalement être protégé par :

- cookie `HttpOnly` ;
- `Secure` ;
- politique `SameSite` adaptée ;
- protection CSRF lorsqu'applicable ;
- rotation ;
- expiration ;
- révocation serveur.

---

## 36. Mobile / desktop

Pour les clients natifs :

- le refresh credential doit être stocké dans un secure storage ;
- il ne doit pas être stocké dans un fichier texte ;
- il ne doit pas être exposé aux logs ;
- les mécanismes de protection de l'OS doivent être utilisés.

---

## 37. Access token

Un refresh peut produire :

    new AccessToken

L'access token n'est pas l'agrégat `Session`.

Il est émis par l'infrastructure à partir du résultat métier.

---

## 38. Access token claims

Les claims d'autorisation éventuellement présents dans l'access token sont dérivés.

Ils doivent respecter :

    UserSecurityVersion
    AuthorizationStateVersion
    MembershipVersion
    RoleVersion
    PermissionSetVersion

lorsque ces versions sont utilisées par le système.

---

## 39. Ne pas utiliser le refresh pour contourner une révocation

Un refresh ne doit jamais restaurer une autorisation supprimée.

Exemple :

    Role removed
            ↓
    AuthorizationStateVersion changed
            ↓
    old access token invalid
            ↓
    RefreshSession

Le refresh peut réussir uniquement si la session reste valide.

Le nouvel access token doit refléter l'autorisation actuelle.

---

## 40. Recalcul d'autorisation

Le refresh peut déclencher une reconstruction de claims dérivés.

Mais :

    claims ≠ source of truth

La source de vérité reste :

    User
    Membership
    Role
    Permission

---

## 41. Traitement métier

### 41.1 Vérifier l'idempotence

Recherche :

    SessionId + RefreshRequestId

Une répétition identique retourne le résultat initial.

### 41.2 Charger Session

Le système charge :

- `SessionId` ;
- `UserId` ;
- status ;
- type ;
- dates ;
- security versions ;
- `RefreshTokenFamilyId` ;
- contexte device ;
- contexte client ;
- version.

### 41.3 Vérifier le statut

Seule :

    Active

est acceptable.

### 41.4 Charger User

Le système vérifie :

    User.Status
    User.SecurityVersion
    User.AuthenticationStateVersion

### 41.5 Vérifier les versions

    Session.UserSecurityVersion
    =
    User.SecurityVersion

et :

    Session.AuthenticationStateVersion
    =
    User.AuthenticationStateVersion

lorsque cette version est utilisée.

### 41.6 Vérifier la session absolue

Le système vérifie :

    RequestedAt
    <
    Session.ExpiresAt

### 41.7 Vérifier l'inactivité

Si applicable :

    RequestedAt
    <
    Session.IdleExpiresAt

### 41.8 Charger RefreshTokenFamily

Le système vérifie :

    Family.Status = Active

### 41.9 Charger RefreshCredential

Le système recherche le credential présenté.

### 41.10 Vérifier l'appartenance

    Credential.SessionId
    =
    Session.SessionId

et :

    Credential.FamilyId
    =
    Session.RefreshTokenFamilyId

### 41.11 Vérifier l'état du credential

Seul :

    Active

est acceptable.

### 41.12 Vérifier l'expiration

    RequestedAt
    <
    Credential.ExpiresAt

### 41.13 Vérifier la preuve cryptographique

Le secret présenté doit correspondre au credential enregistré.

### 41.14 Vérifier le device

Lorsque le binding est activé :

    PresentedDeviceId
    =
    Credential.DeviceId

### 41.15 Vérifier le client

Lorsque le binding est activé :

    PresentedClientApplicationId
    =
    Credential.ClientApplicationId

### 41.16 Vérifier le risque

Une réévaluation peut être effectuée.

Si le risque est trop élevé :

    RefreshRiskTooHigh

### 41.17 Détecter le rejeu

Si :

    Credential.Status = Used

alors le système doit distinguer :

    legitimate retry

de :

    credential replay

Cette distinction doit reposer sur la clé d'idempotence et le contexte de demande.

### 41.18 Traiter un rejeu

En cas de rejeu avéré :

    RefreshTokenFamily.Status = Compromised

et :

    Session.Status = Revoked

La famille entière est invalidée.

### 41.19 Calculer la nouvelle expiration

Le système calcule :

    NewCredentialExpiresAt

dans les limites de :

    RefreshCredentialLifetime
    Session.ExpiresAt
    AbsoluteLifetime

### 41.20 Marquer l'ancien credential comme utilisé

    Credential.Status = Used

### 41.21 Créer le nouveau credential

    Credential N+1

avec :

    Status = Active
    ParentCredentialId = Credential N
    FamilyId = existing family

### 41.22 Mettre à jour la Session

La session conserve :

    SessionId
    UserId
    SessionType
    CreatedAt
    AuthenticatedAt

mais peut mettre à jour :

    LastRefreshedAt
    IdleExpiresAt
    RefreshCount
    CurrentCredentialId
    SessionVersion

### 41.23 Incrémenter la version

    Session.Version += 1

### 41.24 Produire SessionRefreshed

L'agrégat produit :

    SessionRefreshed

### 41.25 Enregistrer l'idempotence

Le résultat est associé à :

    RefreshRequestId

### 41.26 Commit atomique

Le même commit doit garantir :

    old credential marked Used
    +
    new credential Active
    +
    Session updated
    +
    idempotency record
    +
    SessionRefreshed event

---

## 42. Résultat attendu

    Session
    ├── same SessionId
    ├── same UserId
    ├── Status: Active
    ├── same SessionType
    ├── CreatedAt unchanged
    ├── AuthenticatedAt unchanged
    ├── ExpiresAt bounded
    ├── IdleExpiresAt updated when allowed
    ├── LastRefreshedAt updated
    ├── RefreshCount incremented
    ├── CurrentCredentialId updated
    ├── UserSecurityVersion unchanged
    └── Version incremented

---

## 43. Résultat fonctionnel

Structure recommandée :

    RefreshSessionResult
    ├── SessionId
    ├── UserId
    ├── SessionType
    ├── Status
    ├── RefreshedAt
    ├── ExpiresAt
    ├── IdleExpiresAt
    ├── AccessCredentialIssuanceRequired
    ├── RefreshCredentialIssuanceRequired
    ├── RefreshTokenFamilyId
    ├── SessionSecurityVersion
    ├── UserSecurityVersion
    └── SessionVersion

Le résultat métier ne contient pas le refresh secret.

---

## 44. Credential issuance

Après commit, l'infrastructure peut produire :

    NewAccessToken
    +
    NewRefreshToken

Le nouveau refresh token doit correspondre au nouveau :

    RefreshCredentialId

---

## 45. Ancien refresh token

Après succès :

    old refresh token
    → invalid

Il ne doit plus pouvoir être accepté.

---

## 46. Grace period

Une courte période de grâce peut être nécessaire pour gérer :

    concurrent legitimate requests

notamment dans les applications mobiles ou les environnements multi-onglets.

Si une grace period est introduite :

- elle doit être très courte ;
- elle doit être explicitement modélisée ;
- elle ne doit pas permettre deux rotations indépendantes ;
- elle ne doit pas empêcher la détection d'un rejeu réel.

Décision par défaut :

    No grace period

sauf besoin démontré.

---

## 47. Idempotence

Clé :

    SessionId + RefreshRequestId

---

## 48. Pourquoi l'idempotence est importante

Un client peut recevoir :

    RefreshSession succeeds
            ↓
    network failure
            ↓
    response lost

Il peut alors réessayer.

Sans idempotence :

    Credential N
    → refresh
    → Credential N+1

    Credential N
    → retry
    → rejected as replay

Le système pourrait alors confondre un retry réseau avec un vol de credential.

---

## 49. Répétition identique

Une répétition avec le même :

    RefreshRequestId

doit retourner le résultat logique initial.

Elle ne doit pas :

- effectuer une deuxième rotation ;
- créer une nouvelle famille ;
- incrémenter deux fois `RefreshCount` ;
- produire un second événement.

---

## 50. Réutilisation avec autre RequestId

Si un credential déjà consommé est présenté avec :

    RefreshRequestId != original

la demande est considérée comme potentiellement malveillante.

Le système peut déclencher :

    RefreshTokenReplayDetected

et révoquer la famille.

---

## 51. Idempotency conflict

Même :

    RefreshRequestId

mais paramètres différents :

    IdempotencyConflict

---

## 52. Concurrence

### 52.1 Deux refresh simultanés

Un seul refresh doit consommer le credential.

L'autre doit :

- recevoir le résultat idempotent si la demande est identique ;
- ou être traité comme rejeu si la demande est différente.

### 52.2 Refresh contre RevokeSession

Si la révocation gagne :

    SessionRevoked

Si le refresh gagne :

    RevokeSession

doit ensuite invalider la session.

La session ne doit jamais redevenir active après révocation.

### 52.3 Refresh contre RevokeAllUserSessions

Même principe, mais à l'échelle du `User`.

Une version de sécurité globale permet de rendre obsolète le refresh.

### 52.4 Refresh contre ExpireSession

La session ne doit pas être rafraîchie après expiration.

### 52.5 Refresh contre changement de mot de passe

Le changement de mot de passe peut incrémenter :

    User.SecurityVersion

Le refresh doit alors échouer.

### 52.6 Refresh contre désactivation du User

La désactivation gagne si elle est commitée avant le refresh.

Le refresh est refusé.

### 52.7 Refresh contre changement de permission

La session peut rester active.

Le prochain access token doit refléter la nouvelle autorisation.

Le refresh ne doit pas restaurer les anciennes permissions.

### 52.8 Refresh contre changement de Role

Même principe.

### 52.9 Refresh contre suppression du Membership

Le refresh de session globale peut réussir si le `User` reste actif.

Toute tentative d'accès au workspace concerné sera ensuite refusée.

### 52.10 Refresh contre changement de session

Une modification concurrente de la session doit être détectée par la version de concurrence.

---

## 53. Atomicité

Le renouvellement doit être atomique :

    consume old credential
    +
    issue new credential
    +
    update Session
    +
    record idempotency
    +
    append SessionRefreshed

Il est interdit de produire :

    old credential = Used
    +
    new credential = missing
    +
    Session still references old credential

---

## 54. Outbox transactionnelle

`SessionRefreshed` doit être enregistré dans la même transaction que la modification de la session.

Sa publication intervient après commit.

---

## 55. Événement produit : SessionRefreshed

Contenu recommandé :

- `SessionId`
- `UserId`
- `SessionType`
- `RefreshedAt`
- `PreviousCredentialId`
- `NewCredentialId`
- `RefreshTokenFamilyId`
- `RefreshCount`
- `AuthenticationAssuranceLevel`
- `DeviceId`
- `DeviceType`
- `ClientApplicationId`
- `ClientType`
- `RiskLevel`
- `UserSecurityVersion`
- `AuthenticationStateVersion`
- `SessionSecurityVersion`
- `SessionVersion`
- `RefreshRequestId`
- `CorrelationId`

---

## 56. Données interdites dans SessionRefreshed

L'événement ne doit jamais contenir :

- refresh token ;
- access token ;
- cookie ;
- mot de passe ;
- secret cryptographique ;
- code OTP ;
- secret MFA ;
- clé privée ;
- preuve biométrique ;
- contenu complet du credential.

---

## 57. Événement de sécurité

En cas de rejeu :

    SessionRefreshRejected

ou :

    RefreshTokenReplayDetected

peut être produit.

Contenu recommandé :

- `SessionId`
- `UserId`
- `RefreshTokenFamilyId`
- `PresentedCredentialId`
- `DetectedAt`
- `DetectionReason`
- `DeviceId`
- `ClientApplicationId`
- `RiskLevel`
- `CorrelationId`

Aucun secret.

---

## 58. Effets secondaires possibles

Après succès :

    AccessCredentialIssued
    RefreshCredentialIssued
    SessionSecurityProjectionUpdated
    SessionActivityRecorded

Après rejeu :

    RefreshTokenFamilyCompromised
    SessionRevocationRequested
    SecurityIncidentCreated
    SuspiciousSessionNotificationRequested

---

## 59. Pas de notification normale

Un refresh réussi ne doit normalement pas envoyer de notification à chaque rotation.

Sinon une session persistante générerait une quantité excessive de notifications.

---

## 60. Notification de sécurité

Une notification peut être déclenchée lorsque :

- un rejeu est détecté ;
- un refresh arrive depuis un device inhabituel ;
- le risque est élevé ;
- la famille est compromise ;
- la session est révoquée automatiquement.

---

## 61. Audit

Un refresh réussi doit permettre de connaître :

    which Session was refreshed
    which User owns it
    when the refresh occurred
    which credential was replaced
    which credential replaced it
    which credential family was used
    which device was involved
    which client was involved
    what risk level was observed
    which security versions were current
    which Session version resulted

---

## 62. Audit de rejeu

Un rejeu doit permettre de savoir :

    which Session was targeted
    which User was targeted
    which credential was replayed
    which token family was affected
    when the replay occurred
    from which client
    from which device
    what risk context existed
    whether the Session was revoked
    whether the token family was compromised

---

## 63. Confidentialité

Les données suivantes doivent être minimisées :

- IP ;
- user agent ;
- device name ;
- localisation ;
- identifiants externes ;
- détails du client.

Aucun secret d'authentification ne doit être conservé dans l'audit.

---

## 64. Erreurs métier

### SessionNotFound

La session n'existe pas.

### SessionNotActive

La session n'est pas active.

### SessionExpired

La session est expirée.

### SessionRevoked

La session est révoquée.

### UserNotFound

Le User n'existe plus.

### UserSuspended

Le User est suspendu.

### UserLocked

Le User est verrouillé.

### UserDisabled

Le User est désactivé.

### UserRemoved

Le User est supprimé.

### UserSecurityVersionConflict

L'état de sécurité du User a changé.

### AuthenticationStateVersionConflict

L'état d'authentification du User a changé.

### RefreshNotAllowed

La politique interdit le refresh.

### RefreshCredentialNotFound

Le credential n'existe pas.

### RefreshCredentialSessionMismatch

Le credential appartient à une autre session.

### RefreshCredentialFamilyMismatch

Le credential appartient à une autre famille.

### RefreshCredentialExpired

Le credential a expiré.

### RefreshCredentialRevoked

Le credential est révoqué.

### RefreshCredentialAlreadyUsed

Le credential a déjà été utilisé.

### RefreshCredentialInvalid

Le credential présenté n'est pas valide.

### RefreshCredentialIntegrityViolation

La preuve cryptographique du credential n'est pas valide.

### RefreshTokenFamilyNotFound

La famille de refresh n'existe pas.

### RefreshTokenFamilyRevoked

La famille a été révoquée.

### RefreshTokenFamilyCompromised

La famille a été compromise.

### RefreshTokenReplayDetected

Un credential déjà consommé a été rejoué.

### DeviceBindingMismatch

Le credential est lié à un autre appareil.

### ClientBindingMismatch

Le credential est lié à une autre application cliente.

### RefreshLifetimeExceeded

La durée maximale de refresh est dépassée.

### SessionAbsoluteLifetimeExceeded

La durée de vie absolue de la session est dépassée.

### SessionIdleLifetimeExceeded

La session a dépassé sa durée maximale d'inactivité.

### MaximumRefreshCountExceeded

Le nombre maximal de refresh est dépassé.

### RefreshRiskTooHigh

Le niveau de risque interdit le refresh.

### StepUpAuthenticationRequired

Une nouvelle authentification renforcée est nécessaire.

### IdempotencyConflict

Le même identifiant représente une autre intention.

### RefreshConcurrencyConflict

Une rotation concurrente empêche le refresh.

---

## 65. États interdits

    Revoked Session
    → refreshed

    Expired Session
    → refreshed

    Used RefreshCredential
    → accepted as new refresh

    Revoked RefreshTokenFamily
    → new credential issued

    Compromised RefreshTokenFamily
    → new credential issued

    User.SecurityVersion != Session.UserSecurityVersion
    → refresh succeeds

    old credential remains Active
    after successful rotation

    two Active credentials
    for the same rotation position

    RefreshSession creates a new SessionId

    RefreshSession changes UserId

    RefreshSession grants permissions

    RefreshSession restores revoked Membership

    raw refresh token appears in domain event

    RefreshSession succeeds
    without SessionRefreshed

---

## 66. Sécurité

La commande doit garantir que :

- un refresh credential ne peut être utilisé qu'une fois ;
- les credentials sont rotatifs ;
- les familles peuvent être révoquées ;
- un rejeu est détectable ;
- un rejeu entraîne une réponse de sécurité ;
- les sessions révoquées ne peuvent pas être rafraîchies ;
- les sessions expirées ne peuvent pas être rafraîchies ;
- les versions de sécurité sont respectées ;
- les credentials sont liés à la bonne session ;
- les credentials sont liés à la bonne famille ;
- les bindings device/client sont respectés lorsqu'activés ;
- la durée absolue de session est respectée ;
- les limites de refresh sont respectées ;
- les tokens ne sont jamais journalisés ;
- les événements ne contiennent aucun secret ;
- la rotation est atomique ;
- la concurrence ne permet pas deux rotations valides ;
- l'opération est idempotente.

---

## 67. Pas de réauthentification ordinaire

`RefreshSession` n'exige normalement pas une nouvelle authentification interactive.

Cependant, la politique peut imposer :

    StepUpAuthenticationRequired

pour :

- session privilégiée ;
- risque élevé ;
- durée d'inactivité dépassée ;
- changement important du contexte ;
- nouvelle politique de sécurité ;
- accès sensible.

Dans ce cas :

    RefreshSession
    → rejected
    → new AuthenticationProof required

puis :

    CreateSession

ou une commande d'élévation dédiée peut être utilisée selon le workflow.

---

## 68. Privileged Session

Une `Privileged` session peut avoir une politique beaucoup plus stricte :

    short absolute lifetime
    +
    short idle lifetime
    +
    strong authentication
    +
    frequent reauthentication

Le refresh ne doit jamais transformer une session standard en session privilégiée.

---

## 69. Remembered Session

Une session `Remembered` peut utiliser un refresh plus long.

Elle doit cependant respecter :

    absolute maximum lifetime
    +
    device policy
    +
    rotation
    +
    reuse detection
    +
    revocation

---

## 70. Recovery Session

Une `Recovery` session ne doit pas automatiquement devenir une session interactive complète.

Le refresh conserve :

    SessionType = Recovery

jusqu'à une transition explicite.

---

## 71. Impersonation Session

Une session d'impersonation conserve :

    ActorUserId
    SubjectUserId

pendant tout son cycle.

Le refresh ne doit jamais permettre :

    ActorUserId lost

ou :

    SubjectUserId changed

---

## 72. Federated Session

Le refresh peut continuer une session fédérée selon la politique du fournisseur.

Une révocation externe peut toutefois invalider :

    FederationContext

et donc la session.

---

## 73. Session Type immutable

`RefreshSession` ne doit pas modifier :

    SessionType

Une transformation de type doit utiliser une commande dédiée.

---

## 74. Device change

Par défaut :

    DeviceBinding enabled
    → device change rejected

Un changement d'appareil doit normalement passer par :

    CreateSession

après nouvelle authentification.

---

## 75. Client change

Même principe.

Un credential d'une application mobile ne doit pas automatiquement devenir valide pour une autre application si la politique interdit le cross-client refresh.

---

## 76. Refresh depuis plusieurs appareils

Si le binding device n'est pas activé, plusieurs appareils peuvent théoriquement utiliser la même famille.

Cette stratégie est moins restrictive.

La recommandation est :

    one Session
    +
    one RefreshTokenFamily
    +
    explicit device policy

---

## 77. RevokeAllUserSessions

`RevokeAllUserSessions` doit invalider les refresh credentials associés aux sessions du User.

Une fois cette opération commitée :

    old RefreshCredential
    → invalid

Le refresh doit échouer.

---

## 78. RevokeSession

La révocation individuelle doit invalider :

    Session
    +
    RefreshTokenFamily

ou rendre le credential inutilisable via la vérification du statut de session.

La stratégie recommandée est de faire les deux lorsque le stockage le permet.

---

## 79. ExpireSession

L'expiration doit également rendre inutilisables :

    RefreshCredential

et :

    RefreshTokenFamily

---

## 80. Effets sur les Memberships

Aucun.

---

## 81. Effets sur les Roles

Aucun.

---

## 82. Effets sur les Permissions

Aucun changement direct.

Cependant, si les claims d'un nouvel access token sont reconstruits, ils doivent refléter l'état d'autorisation courant.

---

## 83. Idempotence et émission des secrets

L'idempotence doit également traiter le problème du résultat contenant un credential secret.

Le système ne doit pas stocker le refresh token brut dans l'enregistrement d'idempotence.

Deux stratégies sont possibles.

### Strategy A — secure credential recovery

Stocker uniquement :

    CredentialId

et permettre au client de récupérer le credential via un canal sécurisé si la reprise est autorisée.

### Strategy B — request-bound replay

Associer le résultat à une empreinte de requête et conserver le secret dans un composant sécurisé avec durée de vie très courte.

Décision recommandée :

    Secret material is handled outside the domain
    and never stored in ordinary idempotency records.

---

## 84. Concurrence forte

Les opérations suivantes doivent être atomiques :

    Credential N
    → Used

et :

    Credential N+1
    → Active

Une contrainte doit empêcher :

    Credential N
    → Used twice

ou :

    Credential N
    → two children simultaneously

lorsque la politique n'autorise pas cette situation.

---

## 85. Optimistic concurrency

La commande peut utiliser :

    Session.Version

et :

    RefreshCredential.Version

comme garde de concurrence.

Exemple :

    ExpectedSessionVersion = 12
    ExpectedCredentialVersion = 4

---

## 86. Transaction

Le commit logique doit contenir :

    Session update
    +
    old credential invalidation
    +
    new credential creation
    +
    idempotency record
    +
    SessionRefreshed event

---

## 87. Événement de rejeu

En cas de rejeu confirmé :

    RefreshTokenReplayDetected

doit être publié après la décision de sécurité.

La révocation de la famille et de la session doit être cohérente avec l'événement.

---

## 88. Cas limites

### Retry réseau après refresh réussi

Même `RefreshRequestId` :

    return original result

sans nouvelle rotation.

### Retry avec nouveau RequestId

Le credential déjà consommé est présenté avec une autre intention :

    RefreshTokenReplayDetected

### Deux onglets web rafraîchissent simultanément

Un seul doit effectuer la rotation.

Le second doit être géré comme :

    same idempotent operation

ou :

    concurrent refresh

selon la présence du même `RefreshRequestId`.

### Deux appareils utilisent simultanément la même famille

Si le device binding est actif :

    DeviceBindingMismatch

Sinon, le système doit s'appuyer sur la rotation et la détection de rejeu.

### Credential expiré juste avant le commit

La validité doit être vérifiée au moment de l'opération transactionnelle.

### User suspendu pendant le refresh

Si la suspension gagne :

    UserSuspended

### User change son mot de passe pendant le refresh

Si `SecurityVersion` est incrémentée avant le commit :

    UserSecurityVersionConflict

### Session révoquée pendant le refresh

La commande doit échouer.

### Session expirée pendant le refresh

La commande doit échouer.

### Permission retirée pendant le refresh

Le refresh peut réussir.

Le nouveau access token doit cependant refléter l'état actuel des permissions.

### Membership supprimé pendant le refresh

Le refresh peut réussir au niveau de la session globale.

L'accès au workspace concerné sera refusé.

### Role archivé pendant le refresh

Le refresh peut réussir si la session reste valide.

Le nouveau contexte d'autorisation ne doit plus accorder de permissions via le rôle archivé.

### Refresh d'une session privilégiée

Une politique plus courte doit s'appliquer.

Une réauthentification peut être exigée.

### Refresh d'une session de récupération

Le refresh reste limité au workflow de récupération.

### Refresh après compromission

La famille est :

    Compromised

et aucun nouveau credential ne peut être émis.

---

## 89. Checklist de validation

Avant commit :

    Session exists
    Session.Status is Active
    User exists
    User status allows refresh
    UserSecurityVersion matches
    AuthenticationStateVersion matches
    Session absolute lifetime not exceeded
    Session idle lifetime not exceeded
    RefreshTokenFamily exists
    RefreshTokenFamily is Active
    RefreshCredential exists
    RefreshCredential belongs to Session
    RefreshCredential belongs to Family
    RefreshCredential is Active
    RefreshCredential is not expired
    RefreshCredential proof is valid
    RefreshCredential has not been consumed
    Device binding is valid when enabled
    Client binding is valid when enabled
    Risk policy is satisfied
    Refresh policy allows operation
    Maximum refresh count is not exceeded
    SessionType remains unchanged
    Idempotency is verified
    Session version matches
    Credential version matches
    Old credential can be atomically invalidated
    New credential can be atomically created
    SessionRefreshed can be persisted atomically
    No secret enters domain event
    No secret enters ordinary audit

---

## 90. Synthèse

`RefreshSession` renouvelle une session existante sans recréer son identité.

Elle garantit que :

- le `SessionId` reste identique ;
- le `UserId` reste identique ;
- la session est toujours active ;
- le `User` est toujours autorisé à utiliser la session ;
- les versions de sécurité restent cohérentes ;
- le refresh credential appartient à la bonne session ;
- le credential appartient à la bonne famille ;
- le credential est valide et non consommé ;
- le credential est rotatif ;
- l'ancien credential devient inutilisable après rotation ;
- les rejeux sont détectables ;
- un rejeu peut compromettre toute la famille ;
- la session peut être révoquée en cas de compromission ;
- la durée absolue de la session reste bornée ;
- les bindings device/client sont respectés ;
- les permissions ne sont jamais accordées par le refresh ;
- les nouveaux claims éventuels sont dérivés de l'autorisation actuelle ;
- aucun secret n'entre dans les événements ;
- l'opération est idempotente ;
- la rotation est atomique ;
- la concurrence ne permet pas deux rotations indépendantes.

Le résultat conceptuel est :

    Session
    ├── same SessionId
    ├── same UserId
    ├── same SessionType
    ├── active security context
    ├── refreshed lifetime within policy
    ├── RefreshTokenFamily unchanged
    ├── old credential invalidated
    ├── new credential issued
    ├── security versions checked
    ├── authorization remains dynamic
    └── replay protection enforced