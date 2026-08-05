---
id: IDN-CMD-EXPIRE-SESSION
title: ExpireSession
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

aggregate: Session

invariants:
  - IDN-INV-010
  - IDN-INV-012
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
  - ElevateSession.md
  - ExpireSessionElevation.md
  - TerminateSessionElevation.md
  - RevokeSession.md
  - RevokeAllUserSessions.md
  - ../workflows.md
  - ../decision-record.md
---

# ExpireSession

## 1. Objectif

La commande `ExpireSession` matérialise la fin temporelle d'une `Session` devenue invalide en raison de sa durée de vie.

Transition principale :

    Active
        ↓
    ExpireSession
        ↓
    Expired

La commande garantit que :

- la session existe ;
- son échéance est atteinte ;
- son état est encore `Active` ;
- elle devient définitivement `Expired` ;
- elle ne peut plus être rafraîchie ;
- elle ne peut plus être élevée ;
- elle ne peut plus participer à une autorisation ;
- ses refresh credentials deviennent inutilisables ;
- son élévation active éventuelle devient inefficace ;
- son historique reste conservé ;
- l'opération est idempotente ;
- la transition et l'événement sont persistés atomiquement.

---

## 2. Intention métier

La commande répond à l'intention suivante :

    terminate an active Session
    because its allowed lifetime has ended

Elle ne signifie pas :

    explicitly revoke a Session

ni :

    suspend the User

ni :

    remove a Membership

ni :

    change a Role

ni :

    revoke a Permission

---

## 3. Distinction avec RevokeSession

`RevokeSession` correspond à une décision explicite :

    explicit security or user decision
        ↓
    Revoked

`ExpireSession` correspond à une échéance temporelle :

    configured lifetime reached
        ↓
    Expired

Les deux états sont terminaux, mais leur cause métier diffère.

---

## 4. Distinction avec RefreshSession

`RefreshSession` peut prolonger certaines échéances glissantes dans les limites de la politique.

`ExpireSession` s'exécute lorsque la session n'est plus renouvelable ou qu'une échéance terminale est atteinte.

Un refresh ne doit jamais contourner :

    AbsoluteExpiresAt

---

## 5. Agrégat concerné

    Session

La commande modifie un seul agrégat `Session`.

Elle peut consulter :

- le `User` ;
- la politique de durée de session ;
- les refresh credentials ;
- la famille de refresh tokens ;
- l'élévation active ;
- le contexte d'impersonation ;
- le contexte de récupération ;
- les versions de sécurité ;
- l'horloge de confiance.

Elle ne modifie pas directement :

- le `User` ;
- le `Workspace` ;
- le `Membership` ;
- le `Role` ;
- la `Permission`.

---

## 6. Cycle de vie

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

## 7. État Expired

Une session `Expired` :

- existe toujours ;
- conserve son `SessionId` ;
- conserve son `UserId` ;
- conserve son historique ;
- conserve son type ;
- ne peut plus être utilisée ;
- ne peut plus être rafraîchie ;
- ne peut plus être élevée ;
- ne peut plus émettre de nouveaux credentials ;
- ne peut plus participer à une autorisation ;
- ne peut plus redevenir active.

---

## 8. Irréversibilité

Après succès :

    Session.Status = Expired

Aucune commande ordinaire ne doit permettre :

    Expired
        ↓
    Active

Une nouvelle authentification doit créer une nouvelle session.

---

## 9. Types d'expiration

Le modèle peut distinguer plusieurs causes :

    AbsoluteLifetimeReached
    IdleLifetimeReached
    RefreshLifetimeReached
    TemporarySessionLifetimeReached
    RecoverySessionLifetimeReached
    ImpersonationLifetimeReached
    PrivilegedSessionLifetimeReached
    ElevationLifetimeReached
    PolicyLifetimeReached
    ExternalIdentitySessionExpired

---

## 10. Expiration absolue

Chaque session possède une échéance absolue :

    AbsoluteExpiresAt

Condition :

    CurrentTime >= AbsoluteExpiresAt

Cette échéance ne peut pas être repoussée par une activité ordinaire.

---

## 11. Expiration glissante

Une session peut également utiliser une échéance glissante :

    IdleExpiresAt

Cette échéance peut être renouvelée par une activité ou un refresh autorisé.

Elle reste toujours bornée par :

    AbsoluteExpiresAt

---

## 12. Invariant temporel

Condition obligatoire :

    IdleExpiresAt <= AbsoluteExpiresAt

lorsque `IdleExpiresAt` existe.

---

## 13. Expiration par inactivité

La session expire lorsque :

    CurrentTime >= IdleExpiresAt

Cette règle limite les risques liés à une session abandonnée.

---

## 14. Expiration du refresh

Une session peut conserver une période d'usage interactif plus courte que sa période de refresh.

Le modèle doit distinguer :

    AccessCredentialExpiresAt
    RefreshExpiresAt
    AbsoluteExpiresAt

Lorsque le refresh n'est plus autorisé, la session doit devenir terminale selon la politique.

---

## 15. Expiration d'une élévation

L'expiration de `SessionElevation` ne signifie pas nécessairement que la session principale expire.

Exemple :

    Session.Status = Active
    Elevation.Status = Expired

La commande `ExpireSession` concerne la session principale.

L'expiration d'élévation peut produire :

    SessionElevationExpired

---

## 16. Expiration dynamique

La sécurité ne doit pas dépendre exclusivement d'un scheduler.

À chaque décision sensible :

    CurrentTime < effective Session expiry

doit être vérifié.

Ainsi, une session échue est déjà inefficace même si son état persistant n'a pas encore été matérialisé en `Expired`.

---

## 17. Matérialisation

`ExpireSession` matérialise l'état terminal pour :

- l'audit ;
- les projections ;
- les interfaces ;
- le nettoyage ;
- les statistiques ;
- l'invalidation explicite des credentials ;
- les événements métier.

---

## 18. SessionExpirationPolicy

Structure recommandée :

    SessionExpirationPolicy
    ├── PolicyId
    ├── AbsoluteLifetime
    ├── IdleLifetime
    ├── RefreshLifetime
    ├── MaximumAuthenticationAge
    ├── SessionTypeRules
    ├── SlidingExpirationAllowed
    ├── RefreshMayExtendIdleLifetime
    ├── GracePeriod
    ├── ClockTolerance
    ├── ExpireRefreshFamily
    ├── TerminateElevation
    └── NotificationPolicy

---

## 19. SessionType

Valeurs possibles :

    Interactive
    Remembered
    Privileged
    Recovery
    Impersonation
    Service
    Federated
    Temporary

Chaque type peut posséder une politique d'expiration spécifique.

---

## 20. Interactive Session

Une session interactive possède généralement :

- une durée absolue ;
- une durée d'inactivité ;
- une capacité de refresh limitée.

---

## 21. Remembered Session

Une session persistante peut avoir une durée plus longue, mais doit conserver :

- une expiration absolue ;
- une rotation des refresh credentials ;
- une détection de rejeu ;
- une possibilité de révocation à distance.

---

## 22. Privileged Session

Une session privilégiée doit avoir :

- une durée absolue courte ;
- une durée d'inactivité courte ;
- une authentification récente ;
- une réauthentification fréquente.

---

## 23. Recovery Session

Une session de récupération doit expirer rapidement.

Elle ne doit pas rester utilisable après la fin du workflow.

---

## 24. Impersonation Session

Une session d'impersonation doit avoir une durée strictement bornée.

L'expiration termine l'impersonation et conserve :

    ActorUserId
    SubjectUserId

dans l'audit.

---

## 25. Service Session

Une session technique peut avoir une politique différente, mais ne doit jamais être infinie sans décision explicite.

---

## 26. Federated Session

Une session fédérée peut dépendre :

- de l'échéance locale ;
- de l'échéance du fournisseur ;
- d'une session distante ;
- d'une assertion externe.

La politique la plus restrictive doit prévaloir lorsqu'elle est connue.

---

## 27. Temporary Session

Une session temporaire doit expirer à la fin de son workflow ou de sa durée maximale.

---

## 28. Données d'entrée

### 28.1 Données obligatoires

| Donnée | Type | Description |
|---|---|---|
| `SessionId` | `SessionId` | Session à expirer. |
| `ExpiredAt` | Instant | Date métier de l'expiration. |
| `ExpirationReason` | `SessionExpirationReason` | Cause de l'expiration. |
| `ExpirationSource` | `SessionExpirationSource` | Origine du traitement. |
| `ExpireSessionRequestId` | Identifiant | Identifiant idempotent. |

### 28.2 Données facultatives ou conditionnelles

| Donnée | Type | Description |
|---|---|---|
| `ExpectedUserId` | `UserId` | Propriétaire attendu. |
| `ExpectedSessionVersion` | Version | Version attendue. |
| `ExpectedSessionSecurityVersion` | Version | Version de sécurité attendue. |
| `ExpectedExpirationPolicyVersion` | Version | Version de politique attendue. |
| `CorrelationId` | Identifiant | Corrélation du workflow. |
| `Metadata` | Métadonnées contrôlées | Données techniques limitées. |

---

## 29. Sources d'expiration

Valeurs recommandées :

    Scheduler
    RequestValidation
    AuthorizationCheck
    RefreshAttempt
    SessionCleanup
    IdentityProviderSignal
    RecoveryWorkflow
    ImpersonationWorkflow
    PolicyEnforcement
    Migration

---

## 30. Motifs d'expiration

Valeurs recommandées :

    AbsoluteLifetimeReached
    IdleLifetimeReached
    RefreshLifetimeReached
    TemporaryLifetimeReached
    RecoveryLifetimeReached
    ImpersonationLifetimeReached
    PrivilegedLifetimeReached
    ExternalSessionExpired
    PolicyLifetimeReached
    Other

---

## 31. Préconditions

Avant exécution :

- la session existe ;
- la session est encore `Active` ;
- l'échéance applicable est atteinte ;
- la cause d'expiration est cohérente ;
- la source est autorisée ;
- la version de session correspond lorsque requise ;
- la version de sécurité correspond lorsque requise ;
- la version de politique correspond lorsque requise ;
- la demande est idempotente ;
- aucune transition terminale concurrente n'a gagné.

---

## 32. Session déjà expirée

### Même ExpireSessionRequestId

Retourner le résultat initial.

### Nouvelle demande

Retourner :

    SessionAlreadyExpired

Aucun second événement n'est produit.

---

## 33. Session déjà révoquée

Une session `Revoked` reste `Revoked`.

Erreur recommandée :

    SessionAlreadyRevoked

L'expiration ne doit pas remplacer la cause terminale déjà commitée.

---

## 34. Session non encore expirée

Si aucune échéance n'est atteinte :

    SessionNotYetExpired

La commande doit échouer.

---

## 35. Horloge de confiance

L'expiration doit utiliser une horloge serveur de confiance.

Le client ne doit pas imposer l'heure de référence.

`ExpiredAt` peut être fourni par l'orchestration, mais doit être validé contre l'horloge de confiance.

---

## 36. ClockTolerance

Une faible tolérance technique peut être utilisée.

Elle doit être centralisée et strictement bornée.

Elle ne doit pas prolonger arbitrairement une session.

---

## 37. Échéance effective

Structure recommandée :

    EffectiveExpiration
    =
    min(
      AbsoluteExpiresAt,
      IdleExpiresAt when present,
      RefreshExpiresAt when terminal,
      ExternalExpiresAt when applicable,
      WorkflowExpiresAt when applicable
    )

---

## 38. Traitement métier

### 38.1 Vérifier l'idempotence

Recherche :

    SessionId + ExpireSessionRequestId

Une répétition identique retourne le résultat initial.

### 38.2 Charger Session

Le système charge :

- `SessionId` ;
- `UserId` ;
- statut ;
- type ;
- dates ;
- policy ;
- refresh token family ;
- élévation ;
- versions ;
- contexte fédéré ;
- contexte d'impersonation ;
- contexte de récupération.

### 38.3 Vérifier ExpectedUserId

Lorsque fourni :

    Session.UserId = ExpectedUserId

Sinon :

    SessionOwnershipMismatch

### 38.4 Vérifier le statut

Cas :

    Active
    Revoked
    Expired

### 38.5 Charger la politique

Le système charge `SessionExpirationPolicy`.

### 38.6 Calculer l'échéance effective

Le système détermine la première échéance terminale atteinte.

### 38.7 Vérifier que l'échéance est atteinte

Condition :

    ExpiredAt >= EffectiveExpiration

### 38.8 Vérifier la cause

`ExpirationReason` doit correspondre à l'échéance réellement atteinte.

### 38.9 Vérifier les versions attendues

Le système vérifie les versions de session, de sécurité et de politique.

### 38.10 Calculer l'impact

Le système détermine :

    ActiveElevationWillExpire
    RefreshTokenFamilyWillExpire
    AccessCredentialInvalidationRequired
    ImpersonationWillEnd
    RecoveryWorkflowWillEnd

### 38.11 Modifier le statut

    Session.Status = Expired

### 38.12 Enregistrer l'expiration

La session peut conserver :

    ExpiredAt
    ExpirationReason
    ExpirationSource

### 38.13 Terminer l'élévation

Si une élévation est active :

    Session.Elevation.Status = Terminated
    Session.Elevation.TerminationReason = SessionExpired

Elle devient immédiatement inefficace avec sa session parente.

### 38.14 Expirer la famille de refresh tokens

    RefreshTokenFamily.Status = Expired

### 38.15 Empêcher toute nouvelle rotation

Aucun refresh credential ne peut produire de descendant.

### 38.16 Incrémenter SessionSecurityVersion

    Session.SessionSecurityVersion += 1

### 38.17 Incrémenter Session.Version

    Session.Version += 1

### 38.18 Produire SessionExpired

L'agrégat produit :

    SessionExpired

### 38.19 Enregistrer l'idempotence

Le résultat est associé à :

    ExpireSessionRequestId

### 38.20 Commit atomique

Le même commit logique contient :

    Session.Status = Expired
    +
    expiration metadata
    +
    active elevation terminated
    +
    refresh token family expired
    +
    SessionSecurityVersion incremented
    +
    Session.Version incremented
    +
    idempotency record
    +
    SessionExpired event

---

## 39. Résultat attendu

Après succès :

    Session
    ├── same SessionId
    ├── same UserId
    ├── Status: Expired
    ├── same SessionType
    ├── same CreatedAt
    ├── same AuthenticatedAt
    ├── ExpiredAt: set
    ├── ExpirationReason: set
    ├── ExpirationSource: set
    ├── Elevation: ineffective or Expired
    ├── RefreshTokenFamily: Expired
    ├── SessionSecurityVersion: incremented
    └── Session.Version: incremented

---

## 40. Résultat fonctionnel

Structure recommandée :

    ExpireSessionResult
    ├── SessionId
    ├── UserId
    ├── PreviousStatus
    ├── CurrentStatus
    ├── ExpiredAt
    ├── ExpirationReason
    ├── ExpirationSource
    ├── EffectiveExpiration
    ├── ActiveElevationExpired
    ├── RefreshTokenFamilyExpired
    ├── AccessCredentialInvalidationRequired
    ├── ImpersonationEnded
    ├── RecoverySessionEnded
    ├── SessionSecurityVersion
    └── SessionVersion

---

## 41. Invariants

### État terminal

    Session.Status = Expired
    is terminal

### Identité stable

    SessionId remains unchanged

### Propriétaire stable

    UserId remains unchanged

### Type stable

    SessionType remains unchanged

### Refresh interdit

    Expired Session
    cannot be refreshed

### Élévation interdite

    Expired Session
    cannot be elevated

### Autorisation interdite

    Expired Session
    cannot authorize requests

### Échéance atteinte

    ExpiredAt >= EffectiveExpiration

### Famille de refresh terminale

    Session expired
    implies
    RefreshTokenFamily not Active

### Version de sécurité modifiée

    SessionSecurityVersion changes
    after expiration

---

## 42. Événement produit

### SessionExpired

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
- `ExpiredAt`
- `ExpirationReason`
- `ExpirationSource`
- `AbsoluteExpiresAt`
- `IdleExpiresAt`
- `RefreshExpiresAt`
- `EffectiveExpiration`
- `ActiveElevationExpired`
- `ElevationVersion`
- `RefreshTokenFamilyId`
- `RefreshTokenFamilyFinalStatus`
- `AccessCredentialInvalidationRequired`
- `ImpersonationEnded`
- `RecoverySessionEnded`
- `DeviceId`
- `ClientApplicationId`
- `SessionSecurityVersion`
- `SessionVersion`
- `ExpireSessionRequestId`
- `CorrelationId`

---

## 43. Données interdites dans SessionExpired

L'événement ne doit pas contenir :

- access token ;
- refresh token ;
- cookie ;
- mot de passe ;
- secret MFA ;
- code OTP ;
- clé privée ;
- credential WebAuthn brut ;
- donnée biométrique brute ;
- informations personnelles inutiles.

---

## 44. Événements, effets et signaux secondaires

Après commit :

    SessionAccessCredentialInvalidationRequested
    SessionRefreshTokenFamilyExpired
    UserSessionIndexUpdateRequested
    SessionSecurityProjectionUpdated
    ImpersonationEnded
    RecoverySessionEnded
    SessionCleanupRequested

L'expiration de la session rend son élévation inefficace par définition. Une
échéance indépendante utilise `ExpireSessionElevation`. Les noms ci-dessus sont
des signaux internes ou des demandes d'intégration.

---

## 45. Effet sur les autres Sessions

Aucun.

Les autres sessions du même `User` restent inchangées.

---

## 46. Effet sur le User

Aucun changement direct.

Le `User` peut ouvrir une nouvelle session si son état et les politiques l'autorisent.

---

## 47. Effet sur les Memberships

Aucun.

---

## 48. Effet sur les Roles

Aucun.

---

## 49. Effet sur les Permissions

Aucun.

---

## 50. Access tokens

Les access tokens déjà émis doivent devenir inutilisables dans les limites de l'architecture.

Mécanismes possibles :

    introspection
    Session.Status lookup
    SessionSecurityVersion
    short-lived access tokens
    revocation list

---

## 51. Refresh credentials

Après expiration :

    RefreshSession
    → SessionExpired

Même si un credential semble encore cryptographiquement valide.

---

## 52. Idempotence

Clé recommandée :

    SessionId + ExpireSessionRequestId

---

## 53. Empreinte idempotente

L'empreinte inclut au minimum :

    SessionId
    ExpiredAt
    ExpirationReason
    ExpirationSource
    ExpectedUserId
    ExpectedSessionVersion
    ExpectedSessionSecurityVersion
    ExpectedExpirationPolicyVersion

---

## 54. Répétition identique

Une répétition exacte retourne le résultat initial sans :

- produire un second événement ;
- modifier `ExpiredAt` ;
- modifier la cause ;
- incrémenter une nouvelle version ;
- expirer une seconde fois la famille ;
- répéter les invalidations.

---

## 55. Nouvelle demande sur Session expirée

Une nouvelle demande retourne :

    SessionAlreadyExpired

La cause historique initiale reste inchangée.

---

## 56. Conflit d'idempotence

Le même identifiant utilisé pour une autre intention produit :

    IdempotencyConflict

---

## 57. Concurrence

### 57.1 Expire contre RefreshSession

Si le refresh gagne avant l'échéance et prolonge uniquement `IdleExpiresAt`, la commande doit recalculer l'échéance.

Si l'échéance absolue est atteinte, le refresh doit échouer.

Si l'expiration gagne :

    RefreshSession
    → SessionExpired

### 57.2 Expire contre RevokeSession

Une seule transition terminale gagne.

Si la révocation gagne :

    SessionAlreadyRevoked

Si l'expiration gagne :

    SessionAlreadyExpired

### 57.3 Expire contre RevokeAllUserSessions

Même principe.

Une session ne doit produire qu'une seule transition terminale.

### 57.4 Expire contre ElevateSession

Une session échue ne peut pas être élevée.

### 57.5 Expire contre credential issuance

Aucun credential émis après l'expiration ne doit rester utilisable.

### 57.6 Expire contre mise à jour d'activité

Une mise à jour concurrente de `IdleExpiresAt` doit être protégée par version.

---

## 58. Atomicité

Le commit logique contient :

    Session.Status = Expired
    +
    expiration metadata
    +
    active elevation expired
    +
    refresh token family expired
    +
    SessionSecurityVersion incremented
    +
    Session.Version incremented
    +
    idempotency record
    +
    SessionExpired event

---

## 59. Source de vérité

Même avant matérialisation :

    CurrentTime >= EffectiveExpiration

doit suffire à refuser la session.

`Session.Status` matérialisé améliore la cohérence opérationnelle, mais ne constitue pas l'unique protection temporelle.

---

## 60. Outbox transactionnelle

`SessionExpired` doit être enregistré dans la même transaction que le changement d'état.

Sa publication intervient après commit.

---

## 61. États interdits

    Expired Session
    → Active

    Expired Session
    → refreshed

    Expired Session
    → elevated

    Expired Session
    → authorizes request

    Session.Status = Expired
    AND
    RefreshTokenFamily.Status = Active

    Session.Status = Expired
    AND
    active elevation considered valid

    Session expired
    AND
    SessionSecurityVersion unchanged

    SessionExpired event persisted
    AND
    Session.Status remains Active

    Session.Status = Expired
    AND
    SessionExpired event missing

    ExpireSession executed
    before effective expiry

    ExpireSession changes UserId

    ExpireSession changes SessionType

    raw credential appears in event

---

## 62. Effets externes

Après succès, des handlers peuvent :

- invalider les access tokens ;
- nettoyer les credentials ;
- mettre à jour l'index des sessions ;
- actualiser les projections ;
- fermer les WebSockets ;
- supprimer les cookies côté client lors d'une prochaine réponse ;
- clôturer une impersonation ;
- clôturer une récupération ;
- alimenter les statistiques ;
- programmer une purge selon la rétention.

---

## 63. Notifications

Une expiration ordinaire ne nécessite généralement pas de notification.

Une notification peut être utile pour :

- une session privilégiée ;
- une session d'impersonation ;
- une session de récupération ;
- une session de service critique ;
- une expiration inattendue liée à un fournisseur externe.

---

## 64. Audit

L'audit doit permettre de connaître :

- la session concernée ;
- son propriétaire ;
- son type ;
- son statut précédent ;
- son statut final ;
- l'échéance appliquée ;
- la cause ;
- la source ;
- la date d'expiration ;
- l'élévation terminée ;
- la famille de refresh expirée ;
- l'impersonation terminée ;
- la récupération terminée ;
- les versions de sécurité ;
- la demande idempotente ;
- le workflow corrélé.

---

## 65. Questions auxquelles l'audit doit répondre

    which Session expired
    for which User
    when it expired
    which expiry rule was reached
    which policy was applied
    whether an active elevation ended
    whether refresh credentials were invalidated
    whether impersonation ended
    whether a recovery workflow ended
    which Session security version became effective

---

## 66. Confidentialité

Les données suivantes doivent être minimisées :

- adresse IP ;
- user agent ;
- appareil ;
- localisation ;
- fournisseur d'identité ;
- contexte de risque.

Aucun secret ne doit être conservé dans l'événement ou l'audit.

---

## 67. Sécurité

La commande doit garantir que :

- seule une session active peut expirer ;
- une session ne peut pas expirer avant son échéance ;
- une session révoquée ne change pas de cause terminale ;
- une session expirée ne redevient jamais active ;
- aucun refresh n'est possible après expiration ;
- aucune élévation n'est possible après expiration ;
- la famille de refresh devient terminale ;
- l'élévation active devient inefficace ;
- les access tokens peuvent être invalidés ;
- les versions de sécurité sont incrémentées ;
- les courses avec refresh, revoke et elevation sont protégées ;
- aucun secret n'apparaît dans les événements ;
- l'opération est idempotente ;
- la transition et l'événement sont persistés atomiquement.

---

## 68. Erreurs métier

### SessionNotFound

La session n'existe pas.

### SessionAlreadyExpired

La session est déjà expirée.

### SessionAlreadyRevoked

La session est déjà révoquée.

### SessionNotActive

La session n'est pas active.

### SessionNotYetExpired

Aucune échéance terminale n'est atteinte.

### SessionOwnershipMismatch

La session n'appartient pas au `User` attendu.

### ExpirationReasonRequired

Une cause est obligatoire.

### ExpirationSourceInvalid

La source est invalide.

### ExpirationReasonSourceMismatch

La cause n'est pas cohérente avec la source.

### ExpirationPolicyNotFound

La politique n'existe pas.

### ExpirationPolicyVersionConflict

La politique a changé.

### EffectiveExpirationCannotBeCalculated

L'échéance effective ne peut pas être déterminée.

### AbsoluteExpirationNotReached

L'échéance absolue n'est pas atteinte.

### IdleExpirationNotReached

L'échéance d'inactivité n'est pas atteinte.

### RefreshExpirationNotReached

L'échéance de refresh n'est pas atteinte.

### ExternalExpirationNotConfirmed

L'expiration externe n'est pas confirmée.

### SessionVersionConflict

La session a changé.

### SessionSecurityVersionConflict

L'état de sécurité a changé.

### SessionExpirationConflict

Une opération concurrente empêche l'expiration.

### IdempotencyConflict

Le même identifiant représente une autre intention.

---

## 69. Décisions de conception

### Expired est un état terminal

Une nouvelle authentification crée une nouvelle session.

### L'expiration est temporelle

Elle ne remplace pas une révocation explicite.

### La sécurité est dynamique

Une session échue est refusée même avant matérialisation.

### L'expiration absolue ne glisse pas

Aucune activité ne doit la repousser.

### L'expiration glissante reste bornée

    IdleExpiresAt <= AbsoluteExpiresAt

### RefreshSession ne contourne pas l'expiration absolue

### L'élévation peut expirer indépendamment

La session principale ne devient pas nécessairement `Expired`.

### La famille de refresh expire avec la Session

### SessionSecurityVersion est incrémentée

### Les autres agrégats restent inchangés

### Un événement dédié est produit

    SessionExpired

---

## 70. Cas limites

### Session sans IdleExpiresAt

Seule l'expiration absolue s'applique.

### Session sans refresh credential

L'expiration réussit normalement.

### Session avec élévation active

L'élévation devient inefficace.

### Session avec élévation déjà expirée

Aucune transition supplémentaire n'est nécessaire.

### Session fédérée avec expiration distante antérieure

La première échéance applicable prévaut.

### Session expirée techniquement mais non matérialisée

`ExpireSession` matérialise l'état.

### Retry après succès

Le résultat initial est retourné.

### Scheduler en retard

La session est déjà inefficace depuis l'échéance réelle.

### Scheduler en avance

La commande échoue avec `SessionNotYetExpired`.

### Activité concurrente

Une version protège `IdleExpiresAt`.

### Refresh juste avant expiration absolue

Le nouveau credential ne doit pas dépasser `AbsoluteExpiresAt`.

### Horloge légèrement désynchronisée

Une tolérance bornée peut être appliquée.

---

## 71. Checklist de validation

Avant commit :

    Session exists
    Session ownership matches when expected
    Session.Status is Active
    ExpirationPolicy exists
    Effective expiration is calculable
    Effective expiration has been reached
    ExpirationReason matches reached deadline
    ExpirationSource is valid
    ExpectedSessionVersion matches
    ExpectedSessionSecurityVersion matches
    ExpectedExpirationPolicyVersion matches
    Idempotency is verified
    Active elevation impact is calculated
    Refresh token family impact is calculated
    Access credential invalidation requirement is calculated
    Session can transition to Expired
    Refresh token family can transition to Expired
    SessionSecurityVersion can be incremented
    Session.Version can be incremented
    No User state is modified
    No Membership is modified
    No Role is modified
    No Permission is modified
    No raw credential enters the event
    SessionExpired can be persisted atomically

---

## 72. Synthèse

`ExpireSession` matérialise la fin temporelle d'une session devenue invalide.

Elle garantit que :

- la session existe ;
- elle est encore active ;
- une échéance terminale est réellement atteinte ;
- la cause d'expiration est cohérente ;
- la session passe à `Expired` ;
- `SessionId` reste inchangé ;
- `UserId` reste inchangé ;
- `SessionType` reste inchangé ;
- la session ne peut plus être utilisée ;
- la session ne peut plus être rafraîchie ;
- la session ne peut plus être élevée ;
- la famille de refresh tokens devient terminale ;
- l'élévation active devient inefficace ;
- les access tokens peuvent être invalidés ;
- `SessionSecurityVersion` est incrémentée ;
- les autres sessions restent inchangées ;
- le `User`, les memberships, les rôles et les permissions restent inchangés ;
- l'expiration est vérifiée dynamiquement ;
- la sécurité ne dépend pas du scheduler ;
- l'opération est idempotente ;
- les conflits avec refresh, revoke et elevation sont détectés ;
- aucun secret n'entre dans les événements ;
- la transition et l'événement sont persistés atomiquement.

Le résultat conceptuel est :

    Session
    ├── same SessionId
    ├── same UserId
    ├── Status: Expired
    ├── terminal lifecycle state
    ├── refresh disabled
    ├── elevation ineffective
    ├── RefreshTokenFamily expired
    ├── access credentials invalidated or obsolete
    ├── SessionSecurityVersion incremented
    ├── historical context preserved
    └── no impact on other User Sessions
