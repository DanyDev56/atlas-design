---
id: IDN-CMD-CREATE-SESSION
title: CreateSession
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
  - ../events/SessionCreated.md
  - ../events/UserAuthenticated.md
  - RevokeSession.md
  - RevokeAllUserSessions.md
  - ExpireSession.md
  - ../workflows.md
  - ../decision-record.md
---

# CreateSession

## Objectif

La commande `CreateSession` ouvre une nouvelle `Session` pour un `User` dont l’identité et les exigences d’authentification ont été validées.

Elle transforme une preuve d’authentification acceptée en contexte de session exploitable par le système.

```text
validated authentication
↓
CreateSession
↓
active Session
```

La commande garantit que la session :

- appartient à un `User` existant ;
- repose sur une authentification valide ;
- possède une durée de vie bornée ;
- possède un niveau d’assurance explicite ;
- peut être révoquée individuellement ;
- peut être invalidée lorsque l’état de sécurité du `User` change ;
- ne contient pas directement les permissions effectives ;
- ne fige pas définitivement les autorisations du `User` ;
- ne contourne pas le contexte du `Workspace`.

---

## Intention métier

La commande répond à l’intention suivante :

```text
open an authenticated Session
for a verified User
under explicit security constraints
```

Une session représente la continuité d’une authentification déjà réussie.

Elle ne représente pas :

- le mot de passe du `User` ;
- un mécanisme MFA ;
- une permission ;
- une appartenance à un `Workspace` ;
- un rôle ;
- une autorisation définitive ;
- un appareil de confiance permanent.

---

## Distinction entre authentification et session

L’authentification répond à la question :

```text
has the claimant sufficiently proven
the identity of this User?
```

La session répond à la question :

```text
how may this authenticated identity
continue interacting with the system?
```

Le processus recommandé est :

```text
submit credentials
↓
verify identity
↓
evaluate authentication requirements
↓
produce AuthenticationProof
↓
CreateSession
```

`CreateSession` ne doit pas vérifier directement un mot de passe brut.

---

## Distinction avec UserAuthenticated

Deux événements peuvent être distingués.

### UserAuthenticated

Exprime le succès de l’authentification :

```text
the User successfully proved their identity
```

### SessionCreated

Exprime l’ouverture d’une session :

```text
an authenticated continuity context was opened
```

Une authentification peut réussir sans créer de session durable.

Exemples :

- opération ponctuelle ;
- réauthentification renforcée ;
- validation d’une action sensible ;
- élévation temporaire ;
- vérification avant récupération ;
- authentification d’un appel technique non persistant.

---

## Agrégat concerné

```text
Session
```

La commande crée un seul agrégat `Session`.

Elle consulte notamment :

- le `User` ;
- l’état d’authentification du `User` ;
- l’état de sécurité du compte ;
- les méthodes d’authentification utilisées ;
- les exigences MFA ;
- les restrictions de connexion ;
- les limites de sessions ;
- les sessions existantes ;
- les appareils reconnus ;
- les politiques de sécurité ;
- les risques détectés ;
- les éventuelles politiques de workspace applicables après ouverture.

La commande ne modifie pas directement :

- le `User` ;
- les `Memberships` ;
- les `Roles` ;
- les `Permissions` ;
- le `Workspace`.

---

## Chaîne d’autorisation

Une session n’accorde aucune permission par elle-même.

L’autorisation reste résolue dynamiquement :

```text
Session
→ User
→ Membership
→ Role
→ Permission
```

La session prouve seulement que le système reconnaît actuellement le demandeur comme représentant un `User`.

---

## Absence de permission globale dans la Session

La session ne doit pas stocker comme source de vérité :

```text
Permission[]
```

ou :

```text
Role[]
```

Les claims techniques peuvent contenir des données dérivées pour optimiser les lectures, mais ils doivent rester invalidables par version.

Valeurs recommandées :

```text
UserSecurityVersion
AuthenticationStateVersion
SessionVersion
IssuedAt
ExpiresAt
AuthenticationAssuranceLevel
```

Pour un contexte de workspace :

```text
MembershipVersion
RoleVersion
PermissionSetVersion
AuthorizationStateVersion
```

peuvent être utilisés dans un jeton dérivé ou une projection d’autorisation, mais ne constituent pas l’état métier principal de la session.

---

## Session globale et contexte Workspace

La `Session` appartient au `User`.

Elle ne doit pas nécessairement appartenir à un unique `Workspace`.

```text
Session
→ User
```

Le contexte de workspace est sélectionné ou fourni lors de chaque opération nécessitant une autorisation :

```text
Session
+
WorkspaceId
↓
Membership
↓
Role
↓
Permission
```

Cette décision permet à un même `User` d’accéder à plusieurs workspaces avec une seule session, sous réserve de réévaluation de chaque membership.

---

## Variante à session liée au Workspace

Une architecture plus stricte pourrait créer une session distincte par workspace :

```text
Session
→ User
→ Workspace
```

Cette variante simplifie certains claims, mais :

- multiplie les sessions ;
- complique le changement de workspace ;
- mélange authentification et autorisation contextuelle ;
- rend les révocations plus difficiles à raisonner.

Décision recommandée :

```text
Session is User-scoped
Authorization is Workspace-scoped
```

---

## États de Session

Cycle de vie recommandé :

```text
Active
├── RevokeSession
│   ↓
│ Revoked
└── ExpireSession
    ↓
  Expired
```

Valeurs :

```text
SessionStatus
- Active
- Revoked
- Expired
```

Une session terminale ne peut pas redevenir active.

---

## État initial

Après succès :

```text
Session.Status = Active
```

La session possède :

- un `SessionId` ;
- un `UserId` ;
- une date de création ;
- une date de dernière authentification ;
- une date d’expiration absolue ;
- éventuellement une date d’expiration par inactivité ;
- un niveau d’assurance ;
- un contexte d’authentification ;
- une version de sécurité du `User` ;
- des informations contrôlées sur le client ;
- un mécanisme de révocation ;
- un type de session.

---

## SessionType

Valeurs recommandées :

```text
Interactive
Remembered
Privileged
Recovery
Impersonation
Service
Federated
Temporary
```

---

## Interactive

Session ordinaire ouverte après une authentification interactive.

---

## Remembered

Session de durée prolongée, explicitement demandée par le `User`.

Elle exige généralement :

- un appareil suffisamment fiable ;
- une politique autorisant la persistance ;
- un refresh token rotatif ;
- une possibilité de révocation à distance ;
- une durée maximale stricte.

---

## Privileged

Session ou contexte renforcé permettant des opérations sensibles.

Elle doit avoir :

- une assurance élevée ;
- une durée courte ;
- une authentification récente ;
- des facteurs appropriés ;
- une traçabilité renforcée.

Une `Privileged` session ne doit pas être créée à partir d’une authentification faible.

---

## Recovery

Session limitée à un processus de récupération.

Elle ne doit pas permettre l’accès métier ordinaire.

---

## Impersonation

Session créée lorsqu’un acteur autorisé agit au nom d’un autre `User`.

Elle doit distinguer :

```text
ActorUserId
SubjectUserId
```

Elle exige :

- une permission dédiée ;
- un motif ;
- une durée courte ;
- une bannière visible ;
- un audit renforcé ;
- des restrictions fonctionnelles ;
- une impossibilité de masquer l’acteur réel.

---

## Service

Session destinée à une identité technique.

Une identité technique devrait idéalement être représentée par un concept distinct de `User` humain.

Cette valeur ne doit être utilisée que si le modèle accepte explicitement les utilisateurs techniques.

---

## Federated

Session créée après authentification auprès d’un fournisseur d’identité externe.

Elle conserve notamment :

- l’identité du fournisseur ;
- l’identifiant externe ;
- le contexte d’assurance ;
- la date d’authentification externe ;
- la version ou les métadonnées nécessaires à l’audit.

---

## Temporary

Session courte utilisée pour un workflow limité.

Exemples :

- accepter une invitation après validation ;
- confirmer une adresse ;
- effectuer une opération de récupération ;
- finaliser une inscription.

---

## AuthenticationProof

`CreateSession` ne reçoit pas les credentials bruts.

Elle reçoit une preuve abstraite :

```text
AuthenticationProof
```

Structure recommandée :

```text
AuthenticationProof
├── AuthenticationProofId
├── UserId
├── AuthenticationMethodSet
├── AuthenticationAssuranceLevel
├── AuthenticatedAt
├── ValidUntil
├── AuthenticationFlowId
├── IdentityProvider
├── ExternalSubjectId
├── AuthenticationContext
├── RiskAssessmentId
├── DeviceAssessmentId
├── ChallengeResults
├── UserSecurityVersion
└── ProofIntegrity
```

---

## Propriétés d’AuthenticationProof

La preuve doit être :

- authentique ;
- intègre ;
- non expirée ;
- liée au `User` ;
- liée au workflow courant ;
- consommable selon sa politique ;
- non réutilisable lorsque l’usage unique est requis ;
- suffisamment forte pour le type de session demandé ;
- produite par un composant autorisé.

---

## AuthenticationMethod

Valeurs possibles :

```text
Password
Passkey
SecurityKey
AuthenticatorApp
OneTimePassword
EmailLink
SmsCode
RecoveryCode
IdentityProvider
ClientCertificate
AdministrativeRecovery
BiometricDeviceAssertion
```

Ces valeurs décrivent les méthodes observées.

Elles ne doivent pas contenir :

- le mot de passe ;
- la clé privée ;
- le code OTP ;
- le recovery code ;
- le secret TOTP ;
- le contenu du certificat privé.

---

## AuthenticationAssuranceLevel

Valeurs recommandées :

```text
Low
Standard
High
VeryHigh
```

Ou, si une norme externe est adoptée :

```text
AAL1
AAL2
AAL3
```

Le choix exact doit être stabilisé dans un ADR.

---

## Assurance minimale

Le type de session définit un niveau minimal.

Exemple :

```text
Interactive
→ Standard

Remembered
→ Standard or High

Privileged
→ High

Recovery
→ High under restricted capabilities

Impersonation
→ VeryHigh

Service
→ policy-specific
```

---

## SessionDurationPolicy

La durée ne doit pas être décidée arbitrairement par le client.

Elle est calculée à partir de :

```text
SessionDurationPolicy
├── SessionType
├── AbsoluteLifetime
├── InactivityLifetime
├── RefreshLifetime
├── MaximumAuthenticationAge
├── RememberedSessionAllowed
├── RotationRequired
├── DeviceTrustRequirement
├── RiskAdjustment
└── RegulatoryConstraint
```

---

## Expiration absolue

Chaque session possède une limite absolue :

```text
ExpiresAt
```

Une activité continue ne peut pas prolonger indéfiniment cette limite, sauf émission contrôlée d’une nouvelle session ou rotation explicitement modélisée.

---

## Expiration par inactivité

Une session peut également posséder :

```text
IdleExpiresAt
```

ou être évaluée à partir de :

```text
LastActivityAt + InactivityLifetime
```

`LastActivityAt` ne doit pas nécessairement être mis à jour à chaque requête dans l’agrégat principal.

Une projection ou un mécanisme technique adapté peut être utilisé.

---

## Authentication freshness

Certaines actions exigent une authentification récente :

```text
CurrentTime - AuthenticatedAt
≤ MaximumAuthenticationAge
```

La présence d’une session active ne suffit donc pas toujours.

Une nouvelle preuve peut être exigée sans remplacer la session principale.

---

## Données d’entrée

### Données obligatoires

| Donnée | Type | Description |
|---|---|---|
| `SessionId` | `SessionId` | Identifiant de la nouvelle session. |
| `UserId` | `UserId` | Utilisateur authentifié. |
| `AuthenticationProofId` | Identifiant | Preuve d’authentification validée. |
| `SessionType` | `SessionType` | Type de session demandé. |
| `CreatedAt` | Instant | Date métier de création. |
| `CreateSessionRequestId` | Identifiant | Identifiant idempotent. |

### Données facultatives ou conditionnelles

| Donnée | Type | Description |
|---|---|---|
| `RequestedPersistence` | Booléen | Demande de session persistante. |
| `RequestedLifetime` | Durée | Préférence client, bornée par la politique. |
| `DeviceId` | `DeviceId` | Appareil identifié. |
| `DeviceName` | Texte contrôlé | Nom affichable de l’appareil. |
| `DeviceType` | Enum | Type d’appareil. |
| `ClientApplicationId` | Identifiant | Application cliente. |
| `ClientType` | Enum | Web, mobile, desktop, API ou autre. |
| `IpAddress` | Valeur protégée | Adresse réseau observée. |
| `UserAgent` | Texte contrôlé | Agent client observé. |
| `GeoContext` | Donnée approximative | Contexte géographique non précis. |
| `RiskAssessmentId` | Identifiant | Évaluation de risque associée. |
| `DeviceAssessmentId` | Identifiant | Évaluation de l’appareil. |
| `ImpersonationContext` | Value Object | Contexte d’impersonation. |
| `FederationContext` | Value Object | Contexte fédéré. |
| `ExpectedUserSecurityVersion` | Version | Version de sécurité attendue. |
| `CorrelationId` | Identifiant | Corrélation du workflow. |
| `Metadata` | Métadonnées contrôlées | Données techniques limitées. |

---

## Données calculées

La commande calcule ou obtient depuis les politiques :

```text
AuthenticatedAt
AuthenticationAssuranceLevel
ExpiresAt
IdleExpiresAt
RefreshExpiresAt
PersistenceMode
TokenRotationPolicy
UserSecurityVersion
SessionSecurityVersion
```

Le client ne doit pas imposer directement ces valeurs.

---

## SessionId

`SessionId` doit être :

- opaque ;
- non séquentiel ;
- imprévisible ;
- distinct de tout token de session ;
- utilisable dans l’audit ;
- sûr à exposer selon la politique d’API.

---

## SessionId et SessionToken

Il faut distinguer :

```text
SessionId
```

de :

```text
SessionToken
```

`SessionId` identifie l’agrégat.

`SessionToken` prouve la possession de la session auprès d’un mécanisme technique.

Le token brut :

- ne doit pas être stocké en clair ;
- ne doit pas apparaître dans les événements ;
- ne doit pas apparaître dans l’audit ;
- ne doit pas être journalisé ;
- ne doit pas être retourné par le domaine.

---

## Responsabilité du domaine

Le domaine produit l’autorisation de créer la session et les données nécessaires à son émission.

Exemple :

```text
SessionCreated
+
SessionIssuanceSpecification
```

L’infrastructure produit ensuite :

- cookie sécurisé ;
- access token ;
- refresh token ;
- handle de session ;
- secret de rotation.

---

## Pas de token dans l’agrégat

L’agrégat peut conserver :

```text
SessionCredentialReference
CredentialFamilyId
RefreshTokenFamilyId
CredentialVersion
```

mais pas le secret brut.

Une empreinte cryptographique peut être stockée dans un composant de sécurité spécialisé.

---

## Acteur

La commande est généralement initiée par :

- le `User` authentifié ;
- un service d’authentification ;
- un fournisseur d’identité ;
- un `SystemActor` autorisé ;
- un administrateur dans un workflow d’impersonation contrôlé.

L’acteur initiateur et le sujet authentifié doivent être distingués lorsque nécessaire.

---

## Actor et Subject

Session ordinaire :

```text
ActorUserId = SubjectUserId
```

Impersonation :

```text
ActorUserId != SubjectUserId
```

Le système ne doit jamais masquer cette distinction.

---

## Permission requise

Une création de session ordinaire ne dépend pas d’une permission de workspace.

Elle dépend d’une politique d’authentification globale.

Pour l’impersonation :

```text
identity.sessions.impersonate
```

Pour une création administrative exceptionnelle :

```text
identity.sessions.create-for-user
```

Pour une session privilégiée :

```text
identity.sessions.elevate
```

peut être exigée en complément de la preuve d’authentification.

---

## Sources de création

Valeurs recommandées pour `SessionCreationSource` :

```text
InteractiveAuthentication
RememberMeAuthentication
PasskeyAuthentication
FederatedAuthentication
StepUpAuthentication
RecoveryWorkflow
AdministrativeImpersonation
ServiceAuthentication
InvitationWorkflow
AccountActivationWorkflow
Migration
```

---

## Préconditions

Avant exécution :

- le `User` existe ;
- le `User` peut être authentifié ;
- le compte n’est pas supprimé ;
- le compte n’est pas définitivement bloqué ;
- la preuve d’authentification existe ;
- la preuve appartient au `User` ;
- la preuve est valide ;
- la preuve n’a pas expiré ;
- la preuve possède l’intégrité attendue ;
- la preuve satisfait le type de session demandé ;
- les facteurs requis ont été validés ;
- les restrictions de sécurité sont satisfaites ;
- le risque de connexion est acceptable ;
- l’appareil satisfait les exigences applicables ;
- le nombre de sessions autorisé n’est pas dépassé ou une stratégie de remplacement est applicable ;
- la persistance demandée est autorisée ;
- la durée est déterminée par une politique ;
- la version de sécurité du `User` correspond ;
- la demande est idempotente ;
- aucune modification concurrente de sécurité n’a invalidé la preuve.

---

## États du User

Un modèle explicite peut définir :

```text
UserStatus
- Pending
- Active
- Suspended
- Locked
- Disabled
- Removed
```

Règles recommandées :

```text
Active
→ session allowed

Pending
→ only restricted activation or verification Session

Suspended
→ ordinary Session forbidden

Locked
→ ordinary Session forbidden

Disabled
→ Session forbidden

Removed
→ Session forbidden
```

---

## Pending User

Un `User` en attente peut recevoir uniquement une session restreinte lorsque celle-ci est nécessaire pour :

- vérifier une adresse ;
- terminer une activation ;
- accepter certaines conditions ;
- finaliser une invitation ;
- configurer un facteur d’authentification.

Cette session ne doit pas donner accès aux fonctionnalités ordinaires.

---

## Suspended User

La suspension du `User` doit empêcher toute nouvelle session ordinaire.

Erreur :

```text
UserSuspended
```

Une session de recours ou de récupération peut être autorisée par une politique distincte.

---

## Locked User

Un verrouillage de sécurité empêche la création d’une session jusqu’à :

- expiration du verrouillage ;
- récupération ;
- déverrouillage administratif ;
- satisfaction d’un challenge renforcé.

---

## Removed User

Un utilisateur supprimé ne peut pas ouvrir de session.

Ses anciennes sessions doivent être terminales ou invalidées par version de sécurité.

---

## Restrictions de connexion

Le système peut appliquer :

```text
AuthenticationRestrictionPolicy
├── AllowedCountries
├── DeniedCountries
├── AllowedNetworks
├── RequiredClientTypes
├── RequiredDeviceTrust
├── MaximumRiskLevel
├── AllowedIdentityProviders
├── AllowedAuthenticationMethods
├── RequiredAssuranceLevel
└── TimeRestrictions
```

Ces restrictions doivent rester explicites et auditables.

---

## RiskAssessment

Une évaluation de risque peut produire :

```text
RiskLevel
- Low
- Medium
- High
- Critical
```

Décisions possibles :

```text
Low
→ allow

Medium
→ allow with stronger controls

High
→ require step-up or deny

Critical
→ deny and trigger security workflow
```

---

## DeviceTrust

Valeurs recommandées :

```text
Unknown
Recognized
Trusted
Managed
Compromised
Blocked
```

Une session persistante ne devrait pas être créée sur un appareil :

```text
Compromised
or
Blocked
```

---

## Limite de sessions

Une politique peut limiter :

- le nombre total de sessions actives ;
- le nombre par appareil ;
- le nombre par application ;
- le nombre de sessions persistantes ;
- le nombre de sessions privilégiées ;
- le nombre de sessions d’impersonation.

---

## SessionLimitPolicy

Structure recommandée :

```text
SessionLimitPolicy
├── MaximumActiveSessions
├── MaximumRememberedSessions
├── MaximumPrivilegedSessions
├── MaximumSessionsPerDevice
├── OnLimitReached
└── ProtectedSessionTypes
```

---

## OnLimitReached

Valeurs possibles :

```text
RejectNewSession
RevokeOldestSession
RevokeLeastRecentlyUsedSession
RevokeSameDeviceSession
RequireUserSelection
```

---

## Décision recommandée

La politique doit choisir explicitement le comportement.

`CreateSession` ne doit pas révoquer silencieusement une autre session sans que cette stratégie soit déclarée.

Lorsque la révocation d’une autre session est nécessaire, deux approches sont possibles :

### Coordination métier

Une orchestration invoque :

```text
RevokeSession
↓
CreateSession
```

### Politique atomique spécialisée

Une commande dédiée :

```text
ReplaceSession
```

effectue l’opération sous une coordination forte.

Décision par défaut :

```text
CreateSession does not revoke another Session implicitly
```

---

## Traitement métier

### 1. Vérifier l’idempotence

Le système recherche une demande déjà traitée avec :

```text
UserId + CreateSessionRequestId
```

Une répétition identique retourne le résultat initial.

---

### 2. Charger le User

Le système charge :

- `UserId` ;
- statut ;
- version ;
- version de sécurité ;
- restrictions ;
- méthodes disponibles ;
- exigences MFA ;
- état de verrouillage ;
- politiques applicables.

---

### 3. Vérifier l’état du User

Le système refuse les états incompatibles.

---

### 4. Charger AuthenticationProof

Le système charge la preuve sans exposer ses secrets sous-jacents.

---

### 5. Vérifier l’intégrité de la preuve

Le système vérifie :

- l’émetteur ;
- la signature ou la référence de confiance ;
- le sujet ;
- le workflow ;
- les dates ;
- l’usage autorisé ;
- la version de sécurité ;
- l’absence de révocation.

---

### 6. Vérifier l’association au User

```text
AuthenticationProof.UserId = UserId
```

---

### 7. Vérifier la durée de validité

```text
CreatedAt <= AuthenticationProof.ValidUntil
```

---

### 8. Vérifier l’usage de la preuve

Lorsque la preuve est à usage unique, elle ne doit pas avoir été consommée.

---

### 9. Vérifier SessionType

Le système vérifie que le type demandé est autorisé pour :

- le `User` ;
- le workflow ;
- l’application ;
- la méthode d’authentification ;
- le niveau d’assurance ;
- le risque ;
- l’appareil.

---

### 10. Vérifier l’assurance

```text
Proof.AssuranceLevel
>=
RequiredAssuranceLevel(SessionType)
```

---

### 11. Vérifier les facteurs

Le système vérifie que les facteurs requis ont été satisfaits.

---

### 12. Évaluer le risque

Le système charge ou valide `RiskAssessment`.

---

### 13. Évaluer l’appareil

Le système charge ou valide `DeviceAssessment`.

---

### 14. Vérifier les restrictions

Le système applique les restrictions de connexion.

---

### 15. Vérifier la persistance

Si `RequestedPersistence = true`, la politique doit l’autoriser.

---

### 16. Vérifier les sessions existantes

Le système calcule :

```text
ActiveSessionCount
RememberedSessionCount
PrivilegedSessionCount
SameDeviceSessionCount
```

---

### 17. Vérifier les limites

Si une limite est dépassée et qu’aucune stratégie explicite n’est applicable :

```text
SessionLimitExceeded
```

---

### 18. Calculer la durée

Le système détermine :

```text
ExpiresAt
IdleExpiresAt
RefreshExpiresAt
```

à partir de `SessionDurationPolicy`.

---

### 19. Capturer les versions de sécurité

La session conserve au minimum :

```text
UserSecurityVersion
AuthenticationStateVersion
```

Ces versions permettent une invalidation globale sans modifier chaque session synchrone.

---

### 20. Créer l’agrégat Session

```text
Session
├── SessionId
├── UserId
├── Status: Active
├── SessionType
├── CreatedAt
├── AuthenticatedAt
├── ExpiresAt
├── IdleExpiresAt
├── AuthenticationAssuranceLevel
├── AuthenticationMethodSet
├── UserSecurityVersion
├── SessionSecurityVersion
├── DeviceContext
├── ClientContext
└── CreationSource
```

---

### 21. Consommer la preuve si nécessaire

Lorsqu’elle est à usage unique, son utilisation doit être enregistrée atomiquement ou protégée contre les doubles consommations.

---

### 22. Produire SessionCreated

L’agrégat produit :

```text
SessionCreated
```

---

### 23. Enregistrer l’idempotence

La demande et son résultat sont enregistrés.

---

### 24. Commit atomique

La création, la consommation éventuelle de la preuve, l’idempotence et l’événement sont persistés de manière cohérente.

---

## Résultat attendu

Après succès :

```text
Session
├── SessionId
├── UserId
├── Status: Active
├── SessionType
├── CreatedAt
├── AuthenticatedAt
├── ExpiresAt
├── optional IdleExpiresAt
├── optional RefreshExpiresAt
├── AuthenticationAssuranceLevel
├── AuthenticationMethodSet
├── UserSecurityVersion
├── SessionSecurityVersion
├── DeviceContext
├── ClientContext
└── Version: initial version
```

---

## Résultat fonctionnel

Structure recommandée :

```text
CreateSessionResult
├── SessionId
├── UserId
├── SessionType
├── Status
├── CreatedAt
├── AuthenticatedAt
├── ExpiresAt
├── IdleExpiresAt
├── RefreshExpiresAt
├── AuthenticationAssuranceLevel
├── PersistenceMode
├── CredentialIssuanceRequired
├── UserSecurityVersion
└── SessionVersion
```

Le résultat métier ne contient aucun token brut.

---

## Émission des credentials

Après commit, un composant de sécurité peut produire :

```text
SessionCredentialsIssued
```

ou retourner directement au canal sécurisé :

- un cookie de session ;
- un access token ;
- un refresh token ;
- un credential handle.

Cette émission relève de l’infrastructure et doit respecter la spécification produite par le domaine.

---

## Échec d’émission après création

Cas :

```text
Session created
↓
commit succeeds
↓
credential issuance fails
```

Le système ne doit pas laisser durablement une session inutilisable considérée comme pleinement distribuée.

Stratégies possibles :

- émission transactionnelle via un stockage de credentials coordonné ;
- marquage `PendingIssuance` avant activation ;
- révocation compensatoire ;
- credential pré-généré stocké sous forme sécurisée ;
- endpoint idempotent de récupération de l’émission.

---

## Décision recommandée

Pour les systèmes où l’émission peut échouer séparément, introduire :

```text
SessionIssuanceState
- Pending
- Issued
- Failed
```

Le statut métier d’autorisation reste distinct :

```text
SessionStatus
- Active
- Revoked
- Expired
```

Une session `Active` mais non `Issued` ne peut pas être utilisée.

---

## Invariants concernés

### User existant

```text
Session.UserId references an existing User
```

---

### Session active initialement

```text
new Session.Status = Active
```

---

### Preuve valide

```text
Active Session
must originate from a valid AuthenticationProof
```

---

### Preuve suffisamment forte

```text
Proof.AssuranceLevel
>=
Session.RequiredAssuranceLevel
```

---

### Durée bornée

```text
ExpiresAt > CreatedAt
```

et :

```text
ExpiresAt - CreatedAt
<= Policy.MaximumLifetime
```

---

### Session terminale irréversible

Une session révoquée ou expirée ne peut pas être recréée avec le même `SessionId`.

---

### Pas de permission directe

```text
Session does not own Permissions
```

---

### Autorisation contextuelle

```text
Session validity
does not imply Workspace authorization
```

---

### Version de sécurité

```text
Session.UserSecurityVersion
=
User.SecurityVersion at creation time
```

Une divergence ultérieure peut invalider la session.

---

### Secret séparé de l’identifiant

```text
SessionId != SessionToken
```

---

### Aucun secret dans le domaine événementiel

Les événements ne contiennent aucun credential brut.

---

## Événement produit

### SessionCreated

Contenu recommandé :

- `SessionId`
- `UserId`
- `ActorUserId`
- `SubjectUserId`
- `SessionType`
- `SessionStatus`
- `SessionCreationSource`
- `CreatedAt`
- `AuthenticatedAt`
- `ExpiresAt`
- `IdleExpiresAt`
- `RefreshExpiresAt`
- `AuthenticationAssuranceLevel`
- `AuthenticationMethods`
- `IdentityProvider`
- `ClientApplicationId`
- `ClientType`
- `DeviceId`
- `DeviceType`
- `DeviceTrustLevel`
- `RiskLevel`
- `PersistenceMode`
- `Impersonation`
- `Federated`
- `UserSecurityVersion`
- `AuthenticationStateVersion`
- `SessionSecurityVersion`
- `AuthenticationProofId`
- `RiskAssessmentId`
- `DeviceAssessmentId`
- `CreateSessionRequestId`
- `CorrelationId`
- `SessionVersion`

---

## Données réseau dans l’événement

L’adresse IP complète ne doit être incluse que si :

- elle est nécessaire à la sécurité ;
- la politique de rétention est définie ;
- l’accès à l’événement est contrôlé ;
- la réglementation applicable est respectée.

Alternatives :

- référence vers un journal de sécurité ;
- empreinte ;
- préfixe réseau ;
- contexte géographique approximatif ;
- indicateur de changement de réseau.

---

## Données interdites dans l’événement

L’événement ne doit pas contenir :

- de mot de passe ;
- de token de session ;
- d’access token ;
- de refresh token ;
- de cookie ;
- de code OTP ;
- de secret TOTP ;
- de recovery code ;
- de clé privée ;
- de réponse biométrique brute ;
- de credential WebAuthn brut ;
- de claims complets d’un fournisseur ;
- de données personnelles non nécessaires.

---

## Événements secondaires possibles

Après commit :

```text
SessionCredentialIssuanceRequested
UserSessionIndexUpdateRequested
SecurityLoginHistoryUpdateRequested
NewDeviceNotificationRequested
SuspiciousSessionReviewRequested
ImpersonationNotificationRequested
SessionLimitProjectionUpdateRequested
```

---

## Événements non produits

La commande ne produit pas :

```text
MembershipCreated
MembershipReactivated
MembershipRoleChanged
RoleEnabled
PermissionGranted
UserActivated
SessionRevoked
SessionExpired
```

---

## Effet sur les Memberships

Aucun.

L’existence d’une session ne crée pas de membership.

Un `User` authentifié peut ne disposer d’aucun accès à un workspace donné.

---

## Effet sur les Roles

Aucun.

La session ne sélectionne pas définitivement un rôle.

---

## Effet sur les Permissions

Aucun.

Les permissions effectives sont résolues au moment de l’autorisation.

---

## Effet sur les anciennes Sessions

Par défaut, aucun.

La création d’une nouvelle session ne révoque pas les anciennes sessions sans politique ou orchestration explicite.

---

## Idempotence

Clé recommandée :

```text
UserId + CreateSessionRequestId
```

Lorsque `SessionId` est fourni par le demandeur :

```text
SessionId + CreateSessionRequestId
```

peut également être vérifié.

---

## Empreinte idempotente

L’empreinte inclut au minimum :

```text
SessionId
UserId
AuthenticationProofId
SessionType
RequestedPersistence
DeviceId
ClientApplicationId
ImpersonationContext
FederationContext
```

---

## Répétition identique

Une répétition exacte retourne le résultat initial sans :

- créer une seconde session ;
- consommer à nouveau la preuve ;
- produire un second événement ;
- incrémenter les compteurs ;
- générer une nouvelle famille de refresh tokens ;
- envoyer une seconde notification de nouvel appareil.

---

## Conflit d’idempotence

Le même `CreateSessionRequestId` avec une intention différente produit :

```text
IdempotencyConflict
```

---

## Reprise après réponse perdue

Cas :

```text
CreateSession succeeds
↓
transaction commits
↓
response is lost
↓
caller retries
```

Le retry retourne :

- le même `SessionId` ;
- les mêmes dates ;
- les mêmes versions ;
- le même résultat logique ;
- la même spécification d’émission, selon la politique de sécurité.

Le secret précédemment émis ne doit pas nécessairement être réaffiché.

Un mécanisme sécurisé de reprise doit être prévu.

---

## Concurrence

### Deux créations avec la même preuve à usage unique

Une seule commande peut consommer la preuve.

La seconde retourne :

```text
AuthenticationProofAlreadyConsumed
```

ou le résultat idempotent si la demande est identique.

---

### Création contre suspension du User

Si la suspension gagne avant le commit :

```text
UserSuspended
```

Si la création gagne avant la suspension, la suspension doit invalider la session par :

- incrément de `UserSecurityVersion` ;
- révocation explicite ;
- événement de révocation globale.

---

### Création contre verrouillage du User

Même principe.

Une session ne doit pas devenir utilisable si le verrouillage de sécurité a gagné.

---

### Création contre suppression du User

La suppression doit empêcher la création ou invalider immédiatement la session.

---

### Création contre changement de credentials

Un changement sensible peut incrémenter :

```text
User.SecurityVersion
```

`CreateSession` doit vérifier `ExpectedUserSecurityVersion`.

---

### Création contre révocation globale

Si `RevokeAllUserSessions` incrémente une version globale, la nouvelle session doit capturer la version finale cohérente.

---

### Création contre dépassement de limite

Deux créations simultanées peuvent toutes deux observer une capacité restante.

La limite doit être protégée par :

- compteur transactionnel ;
- verrou par `UserId` ;
- contrainte persistante ;
- transaction sérialisée ;
- agrégat de registre de sessions.

---

## Coordination forte

Les invariants suivants nécessitent une protection forte :

```text
AuthenticationProof consumed at most once
```

```text
MaximumActiveSessions is not exceeded
```

```text
Session.UserSecurityVersion
matches current User.SecurityVersion
at commit
```

---

## Atomicité

Le même commit logique doit garantir :

```text
Session created
+
AuthenticationProof consumed when required
+
Session limit reserved
+
Idempotency record
+
SessionCreated event
```

Lorsque les données appartiennent à plusieurs stockages, une stratégie explicite de cohérence est nécessaire.

---

## Outbox transactionnelle

`SessionCreated` doit être enregistré de manière atomique avec la création de la session.

Sa publication intervient après commit.

---

## États interdits

```text
Active Session
without valid User
```

```text
Active Session
for Removed User
```

```text
Active ordinary Session
for Suspended User
```

```text
Session.ExpiresAt <= Session.CreatedAt
```

```text
Session created from expired AuthenticationProof
```

```text
AuthenticationProof consumed twice
```

```text
Privileged Session
with insufficient assurance
```

```text
Remembered Session
when persistence is forbidden
```

```text
Session stores raw password
```

```text
Session event contains raw token
```

```text
Session directly grants Workspace permissions
```

```text
SessionToken used as SessionId
```

```text
Impersonation Session
without ActorUserId
```

```text
Impersonation Session
where actor identity is hidden
```

```text
Session created
without SessionCreated event
```

---

## Effets externes

Après succès, des handlers peuvent :

- émettre les credentials ;
- enregistrer la session dans les index de sécurité ;
- alimenter l’historique de connexion ;
- détecter un nouvel appareil ;
- envoyer une alerte de connexion ;
- mettre à jour les compteurs de sessions ;
- lancer une analyse de sécurité ;
- tracer l’impersonation ;
- appliquer une politique de rétention ;
- mettre à jour les projections administratives.

---

## Notifications

Une notification peut être envoyée lorsque :

- un nouvel appareil est utilisé ;
- un nouveau pays ou réseau est observé ;
- une session persistante est créée ;
- une session privilégiée est créée ;
- une impersonation commence ;
- le niveau de risque est élevé ;
- un fournisseur d’identité inhabituel est utilisé.

La notification ne doit pas révéler de secrets.

---

## Audit

Une création réussie doit permettre de connaître :

- la session créée ;
- le `User` concerné ;
- l’acteur réel ;
- le sujet représenté ;
- le type de session ;
- la source de création ;
- la méthode d’authentification ;
- le niveau d’assurance ;
- la date d’authentification ;
- la date de création ;
- la date d’expiration ;
- le type de client ;
- l’appareil ;
- le niveau de confiance de l’appareil ;
- le niveau de risque ;
- la persistance ;
- l’éventuelle impersonation ;
- l’éventuelle fédération ;
- la version de sécurité ;
- la preuve utilisée ;
- la demande idempotente ;
- le workflow corrélé.

---

## Questions auxquelles l’audit doit répondre

```text
which Session was created
for which User
who initiated the creation
whether actor and subject were different
which authentication methods were used
which assurance level was reached
when authentication occurred
when the Session expires
whether the Session is persistent
which client and device were involved
which risk level was observed
whether impersonation was active
whether federation was involved
which User security version was captured
which AuthenticationProof authorized creation
```

---

## Sécurité

La commande doit garantir que :

- aucune session n’est créée sans authentification valide ;
- les credentials bruts ne traversent pas le domaine ;
- les preuves expirées sont refusées ;
- les preuves à usage unique ne sont pas réutilisées ;
- le niveau d’assurance satisfait le type de session ;
- les sessions privilégiées exigent une authentification forte et récente ;
- les utilisateurs suspendus, verrouillés ou supprimés sont protégés ;
- les restrictions réseau, appareil et risque sont appliquées ;
- la durée est bornée ;
- les sessions persistantes sont contrôlées ;
- les limites de sessions sont protégées contre la concurrence ;
- les tokens ne sont jamais stockés ou journalisés en clair ;
- l’impersonation reste visible et auditable ;
- la session n’accorde pas directement de permissions ;
- les changements de sécurité peuvent invalider les sessions ;
- la création et l’événement sont cohérents ;
- l’opération est idempotente.

---

## Confidentialité

Les données suivantes doivent être minimisées :

- adresse IP ;
- localisation ;
- user agent ;
- nom d’appareil ;
- fournisseur d’identité ;
- identifiant externe ;
- historique de connexion.

Le système doit définir :

- leur finalité ;
- leur durée de conservation ;
- leur niveau d’accès ;
- leur stratégie d’anonymisation ;
- leur usage dans les événements ;
- leur usage dans les notifications.

---

## Erreurs métier

### UserNotFound

Le `User` n’existe pas.

---

### UserPending

Le `User` n’est pas encore autorisé à ouvrir une session ordinaire.

---

### UserSuspended

Le `User` est suspendu.

---

### UserLocked

Le `User` est verrouillé.

---

### UserDisabled

Le `User` est désactivé.

---

### UserRemoved

Le `User` a été supprimé.

---

### AuthenticationProofNotFound

La preuve d’authentification n’existe pas.

---

### AuthenticationProofUserMismatch

La preuve appartient à un autre `User`.

---

### AuthenticationProofInvalid

La preuve n’est pas valide.

---

### AuthenticationProofExpired

La preuve a expiré.

---

### AuthenticationProofAlreadyConsumed

La preuve à usage unique a déjà été utilisée.

---

### AuthenticationProofIntegrityViolation

L’intégrité de la preuve n’est pas vérifiable.

---

### AuthenticationProofSourceNotTrusted

La preuve provient d’une source non autorisée.

---

### AuthenticationFlowMismatch

La preuve appartient à un autre workflow.

---

### AuthenticationAssuranceInsufficient

Le niveau d’assurance est insuffisant.

---

### AuthenticationTooOld

L’authentification n’est plus assez récente.

---

### RequiredAuthenticationMethodMissing

Une méthode obligatoire n’a pas été utilisée.

---

### MultiFactorAuthenticationRequired

Une authentification multifacteur est nécessaire.

---

### SessionTypeNotAllowed

Le type de session demandé n’est pas autorisé.

---

### PersistentSessionNotAllowed

Une session persistante n’est pas autorisée.

---

### RequestedSessionLifetimeNotAllowed

La durée demandée dépasse la politique.

---

### SessionLimitExceeded

Le nombre maximal de sessions est atteint.

---

### RememberedSessionLimitExceeded

Le nombre maximal de sessions persistantes est atteint.

---

### PrivilegedSessionLimitExceeded

Le nombre maximal de sessions privilégiées est atteint.

---

### DeviceSessionLimitExceeded

Le nombre maximal de sessions pour l’appareil est atteint.

---

### DeviceNotAllowed

L’appareil n’est pas autorisé.

---

### DeviceTrustInsufficient

Le niveau de confiance de l’appareil est insuffisant.

---

### DeviceCompromised

L’appareil est considéré comme compromis.

---

### ClientApplicationNotAllowed

L’application cliente n’est pas autorisée.

---

### ClientTypeNotAllowed

Le type de client n’est pas autorisé.

---

### NetworkNotAllowed

Le réseau n’est pas autorisé.

---

### GeographicRestrictionViolation

Le contexte géographique viole une restriction.

---

### AuthenticationRiskTooHigh

Le risque de connexion est trop élevé.

---

### StepUpAuthenticationRequired

Une authentification renforcée est nécessaire.

---

### ImpersonationNotAllowed

L’impersonation n’est pas autorisée.

---

### ImpersonationPermissionRequired

La permission d’impersonation est absente.

---

### ImpersonationReasonRequired

Un motif d’impersonation est obligatoire.

---

### ImpersonationAssuranceInsufficient

Le niveau d’assurance est insuffisant pour l’impersonation.

---

### FederationContextInvalid

Le contexte fédéré est invalide.

---

### IdentityProviderNotAllowed

Le fournisseur d’identité n’est pas autorisé.

---

### ExternalIdentityMismatch

L’identité externe ne correspond pas au `User`.

---

### UserSecurityVersionConflict

L’état de sécurité du `User` a changé.

---

### SessionIdAlreadyExists

Le `SessionId` existe déjà.

---

### SessionCreationConflict

Une opération concurrente empêche la création.

---

### IdempotencyConflict

Le même identifiant représente une intention différente.

---

## Décisions de conception

### CreateSession ne vérifie pas les credentials bruts

La validation des credentials appartient au mécanisme d’authentification.

---

### Une AuthenticationProof explicite est utilisée

Elle sépare la preuve d’identité de la continuité de session.

---

### La Session appartient au User

Elle n’est pas liée définitivement à un workspace.

---

### L’autorisation reste contextuelle

La session ne possède pas directement les rôles ou permissions.

---

### Les tokens sont séparés de SessionId

L’identifiant métier n’est pas un secret d’accès.

---

### Aucun token brut n’apparaît dans les événements

Les credentials appartiennent à un composant de sécurité spécialisé.

---

### La durée est déterminée par une politique

Le client ne choisit pas librement l’expiration.

---

### Le niveau d’assurance est explicite

Les opérations sensibles peuvent exiger une authentification plus forte ou plus récente.

---

### Les sessions terminales sont irréversibles

Une session révoquée ou expirée ne redevient pas active.

---

### CreateSession ne révoque pas implicitement d’autres sessions

Une orchestration ou une commande spécialisée gère le remplacement.

---

### Les versions de sécurité permettent l’invalidation

Un changement de sécurité du `User` peut rendre les sessions existantes invalides.

---

### L’impersonation distingue Actor et Subject

L’acteur réel reste toujours traçable.

---

### Un événement métier dédié est produit

```text
SessionCreated
```

---

## Cas limites

### User actif sans Membership

La session peut être créée.

L’utilisateur est authentifié mais ne possède pas nécessairement d’accès à un workspace.

---

### User avec uniquement des Memberships suspendus

La session globale peut être créée selon la politique du compte.

Toute autorisation dans les workspaces concernés reste refusée.

---

### AuthenticationProof valide au début mais expirée avant commit

La commande doit vérifier la validité au moment du commit ou utiliser une tolérance explicitement définie.

---

### Preuve à usage unique réutilisée par un retry identique

Le système retourne le résultat idempotent initial.

---

### Preuve à usage unique réutilisée pour une autre session

La commande échoue.

---

### Session demandée sans persistance

La durée standard est appliquée.

---

### Session persistante sur appareil inconnu

La politique peut :

- refuser ;
- exiger MFA ;
- réduire la durée ;
- autoriser avec notification.

---

### Session privilégiée depuis une session ordinaire

Une nouvelle `AuthenticationProof` renforcée doit être fournie.

La session ordinaire seule ne suffit pas.

---

### User change de mot de passe pendant la création

La version de sécurité empêche la création avec une preuve obsolète.

---

### Révocation globale concurrente

La session ne doit pas contourner la nouvelle version globale.

---

### Plusieurs onglets soumettent simultanément la même authentification

Une clé idempotente commune évite les sessions dupliquées lorsque l’intention est identique.

---

### Plusieurs appareils s’authentifient simultanément

Les créations sont distinctes et soumises aux limites globales.

---

### Session créée depuis un fournisseur externe indisponible après authentification

La preuve déjà validée peut rester utilisable jusqu’à sa courte expiration, selon la politique.

---

### Provider révoque ultérieurement l’identité

Une synchronisation ou un événement externe doit invalider les sessions concernées.

---

### Horloge légèrement désynchronisée

Une tolérance technique limitée peut être appliquée.

Elle doit être définie centralement et ne pas prolonger arbitrairement la preuve.

---

### Session de récupération

Elle doit être restreinte à son workflow et ne pas devenir une session interactive complète sans authentification supplémentaire.

---

### Impersonation du même User

Elle doit être refusée ou normalisée en session ordinaire.

---

### Retry après émission partielle des credentials

Le système doit éviter de créer une nouvelle famille de credentials sans invalider ou retrouver la précédente.

---

## Checklist de validation

Avant commit :

```text
User exists
User status allows requested SessionType
AuthenticationProof exists
AuthenticationProof belongs to User
AuthenticationProof source is trusted
AuthenticationProof integrity is valid
AuthenticationProof is not expired
AuthenticationProof usage is allowed
AuthenticationProof is not already consumed
AuthenticationProof security version is current
Requested SessionType is allowed
Required assurance level is satisfied
Required authentication methods are present
Authentication freshness is sufficient
Risk assessment is acceptable
Device assessment is acceptable
Client application is allowed
Network restrictions are satisfied
Geographic restrictions are satisfied
Requested persistence is allowed
Session duration policy is resolved
Session limits are respected
Impersonation context is valid when applicable
Federation context is valid when applicable
Expected User security version matches
SessionId is unique
Idempotency is verified
Session credential secrets are excluded
SessionCreated can be persisted atomically
```

---

## Synthèse

`CreateSession` ouvre une continuité d’authentification pour un `User` après validation d’une `AuthenticationProof`.

Elle garantit que :

- le `User` existe ;
- son état autorise le type de session demandé ;
- l’authentification a déjà été validée ;
- la preuve est intègre, non expirée et suffisamment forte ;
- les exigences MFA sont satisfaites ;
- le risque et l’appareil sont acceptables ;
- la durée de session est bornée par une politique ;
- la persistance est explicitement autorisée ;
- les limites de sessions sont respectées ;
- les changements de sécurité concurrents sont détectés ;
- la session ne contient pas directement les permissions ;
- les autorisations restent résolues dans le contexte du workspace ;
- `SessionId` reste distinct des credentials ;
- aucun token brut n’est stocké dans l’agrégat ou les événements ;
- l’impersonation conserve l’identité de l’acteur réel ;
- l’opération est idempotente ;
- la preuve à usage unique ne peut pas être consommée deux fois ;
- la session et l’événement sont persistés de manière cohérente.

Le résultat final est :

```text
Session
├── belongs to User
├── Status: Active
├── explicit SessionType
├── bounded lifetime
├── explicit assurance level
├── captured security versions
├── controlled client and device context
├── no direct Workspace permission
├── no raw credential
└── independently revocable
```