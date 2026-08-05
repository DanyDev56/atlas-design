---
id: IDN-CMD-REVOKE-ALL-USER-SESSIONS
title: RevokeAllUserSessions
status: Draft
owner: Product
version: 1.0.0
last_updated: 2026-08-05

aggregate: User

references:
  - README.md
  - ../entities.md
  - ../aggregates.md
  - ../relationships.md
  - ../value-objects.md
  - ../invariants.md
  - ../permissions.md
  - ../events/AllUserSessionsRevoked.md
  - ../events/UserSessionRevocationRejected.md
  - CreateSession.md
  - RefreshSession.md
  - ElevateSession.md
  - RevokeSession.md
  - ExpireSession.md
  - ../workflows.md
  - ../decision-record.md
---

# RevokeAllUserSessions

## 1. Objectif

La commande `RevokeAllUserSessions` termine de manière explicite toutes les sessions actives appartenant à un même `User`.

Elle représente une révocation globale du contexte d'authentification du compte.

Conceptuellement :

    User
        ↓
    RevokeAllUserSessions
        ↓
    every eligible Session becomes revoked

La commande garantit que :

- le `User` existe ;
- les sessions concernées appartiennent bien à ce `User` ;
- seules les sessions éligibles sont ciblées ;
- chaque session devient inutilisable ;
- aucun refresh credential ne reste valide ;
- aucune élévation ne reste active ;
- aucune nouvelle authentification ne peut être dérivée des anciennes sessions ;
- l'opération est auditée ;
- l'opération est idempotente ;
- l'ensemble de la révocation est cohérent.

---

## 2. Intention métier

La commande répond à l'intention suivante :

    terminate every active Session
    owned by one User

Elle ne signifie pas :

    remove User

ni :

    suspend User

ni :

    lock User

ni :

    remove Membership

ni :

    revoke Role

ni :

    revoke Permission

---

## 3. Pourquoi une commande dédiée

Révoquer toutes les sessions est une opération métier différente de :

    RevokeSession

qui cible une seule session.

Dans de nombreux cas, le métier souhaite exprimer directement :

    "ce compte ne doit plus posséder
    aucune session valide"

plutôt que :

    "révoquer successivement
    chaque Session"

La commande représente donc une intention métier globale.

---

## 4. Cas d'utilisation

Exemples :

- déconnexion de tous les appareils ;
- changement de mot de passe ;
- remplacement des facteurs MFA ;
- récupération de compte ;
- compromission du compte ;
- suspicion de vol de session ;
- suspension du compte ;
- verrouillage du compte ;
- suppression du compte ;
- révocation administrative ;
- incident de sécurité ;
- révocation demandée par un fournisseur d'identité.

---

## 5. Distinction avec RevokeSession

    RevokeSession

agit sur :

    one Session

Tandis que :

    RevokeAllUserSessions

agit sur :

    every eligible Session
    of one User

La granularité est différente.

---

## 6. Distinction avec UserSuspended

Suspendre un compte signifie :

    future authentications forbidden

mais les politiques peuvent également imposer :

    revoke every current Session

La suspension peut donc appeler :

    RevokeAllUserSessions

mais les deux commandes restent indépendantes.

---

## 7. Distinction avec UserLocked

Le verrouillage empêche généralement une nouvelle authentification.

La révocation globale met fin aux authentifications déjà établies.

Les deux concepts sont complémentaires.

---

## 8. Distinction avec PasswordChanged

Le changement de mot de passe peut :

- conserver les sessions existantes ;

ou :

- exiger leur révocation.

Cette décision appartient à la politique de sécurité.

---

## 9. Agrégat concerné

L'intention métier concerne :

    User

et son ensemble de sessions.

Même si les `Session` restent des agrégats autonomes, la commande exprime une décision globale concernant le propriétaire.

Elle coordonne donc la révocation de plusieurs agrégats `Session`.

---

## 10. Pourquoi le User est l'agrégat logique

Le besoin métier s'exprime toujours sous la forme :

    revoke every Session
    owned by this User

Le point d'entrée conceptuel est donc le `User`.

Les `Session` restent les objets effectivement modifiés.

---

## 11. Sessions concernées

Par défaut :

    every Active Session

du `User`.

Ne sont normalement pas concernées :

    Expired Sessions

car elles sont déjà terminales.

Ni :

    already Revoked Sessions

qui sont également terminales.

---

## 12. Sessions éligibles

Une session est éligible si :

    Status = Active

et :

    UserId = TargetUserId

Des politiques plus fines pourront être ajoutées ultérieurement.

---

## 13. Sessions non éligibles

Une session :

    Revoked

ne doit pas être révoquée une seconde fois.

Une session :

    Expired

reste inchangée.

La commande ne modifie pas leur historique.

---

## 14. Effet attendu

Avant :

    User
        ├── Session A → Active
        ├── Session B → Active
        ├── Session C → Expired
        └── Session D → Revoked

Après :

    User
        ├── Session A → Revoked
        ├── Session B → Revoked
        ├── Session C → Expired
        └── Session D → Revoked

---

## 15. Identité des Sessions

Chaque session conserve :

- son `SessionId` ;
- son `UserId` ;
- son historique ;
- son type ;
- sa date de création.

Seul son état évolue.

---

## 16. Irréversibilité

Une session révoquée reste :

    Revoked

Aucune commande ne doit permettre :

    Revoked
        ↓
    Active

Une nouvelle authentification devra produire :

    CreateSession

et un nouveau :

    SessionId

---

## 17. Effet sur les Refresh Credentials

Chaque session révoquée invalide :

- son refresh credential courant ;
- sa famille de refresh tokens ;
- tous les descendants encore utilisables.

Aucun refresh ne doit survivre.

---

## 18. Effet sur les Access Tokens

Selon l'architecture :

- validation par introspection ;
- SessionSecurityVersion ;
- UserSecurityVersion ;
- access tokens courts ;
- token revocation list.

Tous les access tokens doivent devenir inutilisables dans les limites prévues par l'architecture.

---

## 19. Effet sur les Élévations

Toute élévation active est immédiatement rendue inefficace.

Une session terminale ne peut conserver une élévation utilisable.

---

## 20. Effet sur les Permissions

Aucune permission n'est modifiée.

Les rôles restent inchangés.

Les memberships restent inchangés.

Le compte reste inchangé.

Seules les sessions disparaissent comme contexte d'authentification valide.

---

## 21. Effet sur le User

La commande ne modifie pas :

- le profil ;
- les rôles ;
- les permissions ;
- les memberships ;
- les facteurs MFA ;
- le mot de passe.

Elle agit uniquement sur les sessions.

---

## 22. Effet sur la Sécurité

Après succès :

aucune session appartenant au `User` ne doit encore être capable :

- d'être rafraîchie ;
- d'être élevée ;
- d'autoriser une requête ;
- d'obtenir un nouveau credential.

Le compte devra créer une nouvelle session par authentification.

---

## 23. Acteurs possibles

La commande peut être initiée par :

- le propriétaire du compte ;
- un administrateur ;
- un moteur de sécurité ;
- un workflow de récupération ;
- un fournisseur d'identité ;
- un `SystemActor`.

---

## 24. Self-service

Le `User` demande :

    Sign out everywhere

Toutes ses sessions deviennent terminales.

Selon la politique :

la session ayant déclenché l'opération peut également être révoquée.

---

## 25. Révocation administrative

Un administrateur autorisé peut terminer toutes les sessions d'un autre utilisateur.

Une permission spécifique est recommandée :

    identity.sessions.revoke-all

---

## 26. Révocation système

Le système peut déclencher automatiquement la commande après :

- compromission ;
- rejeu de refresh token ;
- récupération de compte ;
- changement majeur de sécurité ;
- détection de fraude.

---

## 27. Philosophie de la commande

La commande exprime une seule intention :

    ce User
    ne doit plus posséder
    aucune Session active

La manière dont chaque session est techniquement révoquée reste une décision d'implémentation.

---

## 28. Stratégies possibles

Deux approches existent :

### Révocation matérialisée

Chaque `Session` est effectivement modifiée.

ou

### Révocation logique

Une augmentation du :

    UserSecurityVersion

rend toutes les sessions obsolètes.

Le projet peut utiliser l'une ou combiner les deux.

---

## 29. Recommandation

Pour Atlas, la stratégie recommandée est hybride :

- incrémenter `UserSecurityVersion` ;
- révoquer explicitement toutes les sessions persistées ;
- produire un historique complet ;
- conserver un audit individuel de chaque session.

Cette approche maximise la sécurité et la traçabilité.

---

## 30. Suite du document

Les sections suivantes détailleront :

- les stratégies de révocation ;
- les politiques ;
- les différents types de sessions ;
- les données d'entrée ;
- les préconditions ;
- le traitement métier ;
- les événements ;
- l'idempotence ;
- la concurrence ;
- les invariants ;
- les erreurs métier ;
- l'audit ;
- les cas limites.

---

## 31. Types de Sessions

Le système peut distinguer plusieurs types de sessions :

    Interactive
    Remembered
    Privileged
    Recovery
    Impersonation
    Service
    Federated
    Temporary

Toutes ne sont pas forcément révoquées selon les mêmes règles.

---

## 32. Interactive Session

Les sessions interactives représentent le cas principal.

Elles sont toujours concernées par :

    RevokeAllUserSessions

sauf politique contraire.

---

## 33. Remembered Session

Une session persistante ("remember me") doit être révoquée.

La révocation doit empêcher :

- toute réutilisation du cookie persistant ;
- toute rotation de refresh credential ;
- toute restauration automatique de session.

---

## 34. Privileged Session

Une session privilégiée est également concernée.

Sa révocation est particulièrement importante après :

- changement de mot de passe ;
- récupération de compte ;
- compromission ;
- changement de rôle administratif.

---

## 35. Recovery Session

Une session de récupération doit être révoquée lorsque :

- la récupération est terminée ;
- un nouvel accès est obtenu ;
- le compte retrouve un état normal.

Une ancienne session de récupération ne doit jamais rester valide.

---

## 36. Impersonation Session

Une session d'impersonation peut être révoquée :

- parce que le sujet est concerné ;
- parce que l'acteur est concerné ;
- parce que le workflow se termine.

Le système doit distinguer :

    ActorUserId

et

    SubjectUserId

---

## 37. Session de Service

Les sessions techniques peuvent être exclues de certaines politiques.

Exemple :

    revoke all human Sessions

sans interrompre immédiatement les intégrations techniques.

Cette décision appartient à la politique métier.

---

## 38. Session Fédérée

Une session fédérée peut nécessiter :

- une révocation locale ;
- une notification vers le fournisseur d'identité ;
- une déconnexion distante.

Les deux opérations doivent rester indépendantes.

---

## 39. Session Temporaire

Une session temporaire est révoquée comme une session classique.

Son caractère temporaire ne la protège pas d'une révocation globale.

---

## 40. Politique de sélection

Toutes les sessions d'un User ne sont pas nécessairement concernées.

Une politique peut sélectionner :

    every Session

ou

    every Human Session

ou

    every Interactive Session

ou

    every Session except current one

ou

    every Session except trusted devices

La politique doit être explicite.

---

## 41. Politique recommandée

Pour Atlas :

    every Active Session

est la règle par défaut.

Cela évite toute ambiguïté.

---

## 42. Exclusion éventuelle de la session courante

Certaines applications proposent :

    Sign out everywhere
    except this device

Cette variante ne doit pas être codée implicitement.

Elle doit être pilotée par une politique ou un paramètre explicite.

---

## 43. Paramètre KeepCurrentSession

Structure possible :

    KeepCurrentSession

Valeurs :

    true
    false

Lorsque :

    true

la session ayant déclenché la commande reste active.

Toutes les autres sont révoquées.

---

## 44. Recommandation

La valeur par défaut recommandée est :

    KeepCurrentSession = false

Le comportement est alors simple :

    aucune Session
    ne reste active

---

## 45. Politique de sécurité

Certaines situations imposent :

    KeepCurrentSession = false

par exemple :

- récupération de compte ;
- changement de mot de passe imposé ;
- compromission ;
- suspicion de vol de refresh token ;
- incident de sécurité.

---

## 46. Révocation après changement de mot de passe

Deux politiques existent.

### Politique permissive

Le changement de mot de passe conserve les sessions existantes.

### Politique stricte

Le changement de mot de passe appelle automatiquement :

    RevokeAllUserSessions

La seconde est recommandée pour un niveau de sécurité élevé.

---

## 47. Révocation après récupération de compte

Après une récupération réussie :

toutes les anciennes sessions doivent être considérées comme suspectes.

Le workflow recommandé est :

    AccountRecoveryCompleted
        ↓
    RevokeAllUserSessions
        ↓
    CreateSession

---

## 48. Révocation après remplacement MFA

Lorsqu'un facteur MFA est remplacé :

les anciennes sessions peuvent devenir non fiables.

La politique peut imposer une révocation globale.

---

## 49. Révocation après compromission

Une compromission détectée implique généralement :

    terminate every active Session

avant toute nouvelle authentification.

---

## 50. Révocation après suspension

Lorsqu'un compte est suspendu :

la politique recommandée est :

    suspend User
        +
    revoke every Session

Le compte ne possède alors plus aucun contexte d'authentification valide.

---

## 51. Révocation après verrouillage

Même principe.

Le verrouillage interdit les futures connexions.

La révocation termine les connexions déjà ouvertes.

---

## 52. Révocation après suppression

Avant suppression définitive du compte :

les sessions doivent être terminées.

La suppression ne doit jamais laisser une session active.

---

## 53. Révocation après changement d'adresse email

Selon la politique :

- aucune action ;

ou

- révocation de toutes les sessions.

Cette décision dépend du niveau de sécurité recherché.

---

## 54. Révocation imposée par le fournisseur d'identité

Un IdP peut signaler :

- logout global ;
- révocation de session ;
- compromission.

Le domaine peut alors lancer :

    RevokeAllUserSessions

---

## 55. Révocation par moteur de risque

Le moteur de risque peut détecter :

- activité inhabituelle ;
- localisation impossible ;
- appareil compromis ;
- comportement suspect.

La politique peut imposer :

    revoke every Session

---

## 56. RevocationPolicy

La commande peut être gouvernée par :

    UserSessionRevocationPolicy

Structure recommandée :

    UserSessionRevocationPolicy
    ├── PolicyId
    ├── EligibleSessionTypes
    ├── KeepCurrentSession
    ├── RevokeRefreshFamilies
    ├── TerminateElevations
    ├── IncrementUserSecurityVersion
    ├── IncrementSessionSecurityVersion
    ├── NotifyUser
    ├── NotifyAdministrators
    ├── IdentityProviderLogout
    ├── AuditLevel
    └── Version

---

## 57. UserSecurityVersion

La stratégie recommandée consiste à incrémenter :

    User.SecurityVersion

Cette version invalide immédiatement toutes les anciennes sessions lors des vérifications de sécurité.

---

## 58. Pourquoi incrémenter UserSecurityVersion

Même si une session n'a pas encore été matérialisée comme :

    Revoked

elle devient immédiatement inutilisable lors des contrôles de sécurité.

Cette protection évite toute fenêtre de vulnérabilité.

---

## 59. SessionSecurityVersion

Chaque session révoquée incrémente également :

    SessionSecurityVersion

Cette version protège :

- les access tokens ;
- les refresh tokens ;
- les claims ;
- les caches.

---

## 60. Double protection

La combinaison recommandée est :

    UserSecurityVersion
        +
    SessionSecurityVersion

La première invalide globalement le compte.

La seconde protège individuellement chaque session.

---

## 61. Sources de révocation

La commande peut être déclenchée par différentes origines.

Valeurs recommandées :

    UserRequested
    AdministrativeAction
    SecurityPolicy
    SecurityIncident
    CredentialChange
    PasswordChanged
    MFAChanged
    AccountRecovery
    AccountSuspended
    AccountLocked
    AccountRemoved
    IdentityProviderLogout
    IdentityProviderRevocation
    RiskEngine
    RefreshTokenReplay
    FraudDetection
    SystemMaintenance
    Migration
    ComplianceRequirement

Chaque source doit être auditée.

---

## 62. RevocationReason

Le motif métier décrit pourquoi les sessions sont terminées.

Exemples :

    UserRequestedSignOutEverywhere
    PasswordChanged
    AuthenticationFactorsChanged
    AccountRecovered
    AccountCompromised
    DeviceCompromised
    RefreshCredentialReplay
    UserSuspended
    UserLocked
    UserRemoved
    AdministrativeRevocation
    IdentityProviderLogout
    SecurityPolicy
    RiskThresholdExceeded
    FraudDetected
    Migration
    Other

Le motif est indépendant de la source.

---

## 63. Source et Motif

Exemple :

    Source:
        RiskEngine

    Reason:
        RiskThresholdExceeded

ou

    Source:
        AdministrativeAction

    Reason:
        AdministrativeRevocation

Les deux informations répondent à des questions différentes.

---

## 64. Données d'entrée

### 64.1 Données obligatoires

| Donnée | Type | Description |
|---|---|---|
| `UserId` | `UserId` | Utilisateur concerné. |
| `RevokedAt` | Instant | Date métier. |
| `RevokedBy` | `UserId` ou `SystemActor` | Initiateur. |
| `RevocationSource` | `SessionRevocationSource` | Origine de la révocation. |
| `RevocationReason` | `SessionRevocationReason` | Motif métier. |
| `RevokeAllSessionsRequestId` | Identifiant | Clé d'idempotence. |

### 64.2 Données facultatives

| Donnée | Type | Description |
|---|---|---|
| `KeepCurrentSession` | Booléen | Conserver la session appelante. |
| `CurrentSessionId` | `SessionId` | Session déclenchante. |
| `PolicyId` | Identifiant | Politique appliquée. |
| `SecurityIncidentId` | Identifiant | Incident associé. |
| `RiskAssessmentId` | Identifiant | Évaluation de risque. |
| `IdentityProviderReference` | Identifiant | Référence externe. |
| `ExpectedUserSecurityVersion` | Version | Contrôle optimiste. |
| `CorrelationId` | Identifiant | Corrélation workflow. |
| `Metadata` | Métadonnées | Données techniques contrôlées. |

---

## 65. Données calculées

La commande détermine notamment :

    EligibleSessions
    RevokedSessionCount
    SkippedSessionCount
    CurrentSessionPreserved
    UserSecurityVersion
    RefreshFamiliesInvalidated
    ElevationsTerminated

Le client ne fournit jamais ces valeurs.

---

## 66. Préconditions

Avant exécution :

- le User existe ;
- le User n'est pas supprimé ;
- l'acteur est autorisé ;
- la politique est valide ;
- la demande est idempotente ;
- les paramètres sont cohérents ;
- les versions attendues correspondent lorsque fournies.

---

## 67. Vérification du User

Le système charge :

    UserId
    Status
    UserSecurityVersion

Le User reste la racine logique de la commande.

---

## 68. États possibles du User

Exemples :

    Active
    Pending
    Suspended
    Locked
    Disabled
    Removed

Toutes les politiques ne permettent pas les mêmes comportements.

---

## 69. User Active

Cas standard.

La commande peut poursuivre normalement.

---

## 70. User Suspended

La révocation est généralement autorisée.

Elle accompagne souvent la suspension.

---

## 71. User Locked

Même logique.

Le verrouillage n'empêche pas la révocation.

---

## 72. User Disabled

Les sessions restantes peuvent être terminées.

La commande reste valide.

---

## 73. User Removed

Deux stratégies existent.

### Refus

Le User n'existe plus.

ou

### Acceptation

Les sessions résiduelles sont nettoyées.

La politique du projet doit être explicite.

---

## 74. Recommandation

Pour Atlas :

    UserRemoved

doit être traité comme un succès technique de nettoyage lorsqu'il subsiste encore des sessions persistées.

---

## 75. Chargement des Sessions

Le système récupère :

    every eligible Session

du User.

Chaque session conserve son autonomie d'agrégat.

---

## 76. Sessions récupérées

Le chargement retourne notamment :

- SessionId
- Status
- SessionType
- RefreshTokenFamily
- Elevation
- SessionSecurityVersion
- Version

---

## 77. Aucune Session active

Cas :

    EligibleSessions = Ø

La commande peut réussir.

Le résultat indique :

    RevokedSessionCount = 0

Cette approche simplifie les workflows.

---

## 78. Pourquoi réussir malgré zéro Session

L'intention métier est :

    ensure User owns
    no active Session

Si cette condition est déjà satisfaite :

la commande est déjà accomplie.

---

## 79. Sessions déjà terminales

Les sessions :

    Revoked

ou

    Expired

ne sont pas modifiées.

Leur historique reste inchangé.

---

## 80. KeepCurrentSession

Lorsque :

    KeepCurrentSession = true

la session appelante est retirée de la sélection.

Toutes les autres restent éligibles.

---

## 81. Validation de KeepCurrentSession

Si :

    KeepCurrentSession = true

alors :

    CurrentSessionId

devient obligatoire.

---

## 82. Session courante introuvable

Si :

    CurrentSessionId

n'appartient pas au User,

la commande échoue.

Erreur recommandée :

    CurrentSessionNotOwnedByUser

---

## 83. Session courante déjà terminale

Si la session courante est déjà :

    Revoked

ou

    Expired

elle ne peut pas être conservée.

Le système poursuit simplement avec les autres sessions.

---

## 84. Politique stricte

Certaines politiques interdisent :

    KeepCurrentSession

Par exemple après :

- compromission ;
- récupération de compte ;
- fraude.

La commande ignore alors ce paramètre ou retourne une erreur métier.

---

## 85. Politique permissive

Pour :

    Sign out everywhere
    except this device

la conservation est autorisée.

Toutes les autres sessions deviennent terminales.

---

## 86. Comptage

Le système détermine :

    TotalSessions

    EligibleSessions

    RevokedSessions

    AlreadyTerminalSessions

Ces valeurs servent à l'audit et au résultat métier.

---

## 87. Sessions d'impersonation

Deux politiques sont possibles.

### Révoquer

Toute session appartenant au User.

### Conserver

Les sessions où le User est uniquement le sujet représenté.

Atlas devra documenter ce choix explicitement.

---

## 88. Recommandation pour Atlas

La recommandation est :

toute session dont :

    Session.UserId

correspond au User ciblé

est concernée.

Le fait qu'elle soit une session d'impersonation ne change pas cette règle.

---

## 89. Sessions de service

Les sessions techniques peuvent être :

- incluses ;

ou

- exclues.

La politique décide.

Le comportement ne doit jamais être implicite.

---

## 90. Politique par défaut

La politique recommandée est :

    revoke every Active Session

quel que soit son type,

sauf exclusion explicitement définie par la politique métier.

---

## 91. Traitement métier

La commande suit les étapes suivantes :

1. vérifier l'idempotence ;
2. charger le `User` ;
3. charger les sessions éligibles ;
4. appliquer la politique ;
5. sélectionner les sessions à révoquer ;
6. incrémenter `UserSecurityVersion` ;
7. révoquer chaque session ;
8. produire les événements ;
9. enregistrer l'idempotence ;
10. effectuer le commit atomique.

---

## 92. Vérifier l'idempotence

Le système recherche :

    UserId
    +
    RevokeAllSessionsRequestId

Une répétition identique retourne le résultat initial.

---

## 93. Charger le User

Le système charge :

- UserId
- Status
- UserSecurityVersion
- Version

---

## 94. Vérifier le User

Le User doit satisfaire la politique.

Par exemple :

    Removed User

peut être accepté ou refusé selon la stratégie retenue.

---

## 95. Charger les Sessions

Le système récupère toutes les sessions éligibles.

Exemple :

    Session A
    Session B
    Session C
    Session D

---

## 96. Appliquer la politique

La politique décide notamment :

- conserver la session courante ;
- exclure certains types ;
- notifier le User ;
- contacter l'Identity Provider ;
- interrompre les intégrations ;
- produire certains événements.

---

## 97. Construire la liste finale

Après application de la politique :

    SelectedSessions

est déterminée.

Cette liste est immuable pendant le traitement.

---

## 98. Incrémenter UserSecurityVersion

Avant toute révocation :

    User.UserSecurityVersion += 1

Cette étape invalide immédiatement toutes les anciennes sessions lors des vérifications de sécurité.

---

## 99. Pourquoi commencer par UserSecurityVersion

Même si une erreur survient plus tard dans un traitement asynchrone,

la nouvelle version protège déjà le compte.

Cette stratégie réduit les fenêtres de vulnérabilité.

---

## 100. Parcours des Sessions

Pour chaque session :

    foreach Session
        revoke Session

Chaque session reste responsable de son propre état.

---

## 101. Révocation d'une Session

La transition est :

    Active
        ↓
    Revoked

Les métadonnées de révocation sont renseignées.

---

## 102. Métadonnées

Chaque session peut enregistrer :

    RevokedAt
    RevokedBy
    RevocationReason
    RevocationSource

Ces informations restent propres à chaque session.

---

## 103. Élévation

Si une session possède :

    Active Elevation

celle-ci devient immédiatement :

    Revoked

ou

    Ineffective

selon le modèle retenu.

---

## 104. Famille de Refresh Tokens

Chaque famille passe à :

    Revoked

ou

    Compromised

si le motif correspond à une compromission.

---

## 105. SessionSecurityVersion

Chaque session incrémente :

    SessionSecurityVersion

Cette version protège les credentials déjà distribués.

---

## 106. Session Version

Chaque agrégat Session incrémente :

    Version

Cette version protège la concurrence.

---

## 107. Événement individuel

Chaque session produit :

    SessionRevoked

L'historique reste complet.

---

## 108. Événement global

Une fois toutes les sessions traitées :

le User produit :

    AllUserSessionsRevoked

Cet événement représente l'intention métier globale.

---

## 109. Pourquoi deux niveaux d'événements

Les événements individuels permettent :

- l'audit détaillé ;
- les projections ;
- les statistiques.

L'événement global représente :

    une seule décision métier

concernant le User.

Les deux sont complémentaires.

---

## 110. Enregistrement de l'idempotence

Le résultat final est associé à :

    RevokeAllSessionsRequestId

---

## 111. Commit

Le même commit logique garantit :

    UserSecurityVersion incremented
        +
    Sessions revoked
        +
    Refresh families revoked
        +
    Elevations terminated
        +
    Session events
        +
    Global event
        +
    Idempotency record

---

## 112. Résultat attendu

Après succès :

    User
        ├── Session A → Revoked
        ├── Session B → Revoked
        ├── Session C → Revoked
        └── UserSecurityVersion++

---

## 113. Résultat fonctionnel

Structure recommandée :

    RevokeAllUserSessionsResult
    ├── UserId
    ├── RevokedSessionCount
    ├── AlreadyTerminalCount
    ├── PreservedCurrentSession
    ├── UserSecurityVersion
    ├── RevokedAt
    ├── RevocationReason
    ├── RevocationSource
    ├── PolicyId
    └── CorrelationId

---

## 114. Invariants

### Plus aucune Session active

Après succès :

    every eligible Session

est :

    Revoked

---

### User inchangé

La commande ne modifie pas :

- identité ;
- email ;
- rôles ;
- permissions ;
- memberships.

---

### UserSecurityVersion

Elle est toujours strictement supérieure à la précédente.

---

### SessionSecurityVersion

Chaque session révoquée possède une nouvelle version de sécurité.

---

### Élévation

Aucune élévation active ne subsiste.

---

### Refresh

Aucune famille de refresh token active ne subsiste.

---

## 115. Événement principal

### AllUserSessionsRevoked

Contenu recommandé :

- UserId
- RevokedAt
- RevokedBy
- RevocationSource
- RevocationReason
- RevokedSessionCount
- AlreadyTerminalCount
- PreservedCurrentSession
- UserSecurityVersion
- PolicyId
- CorrelationId

---

## 116. Événements secondaires

La commande peut produire :

    SessionRevoked
    SessionElevationTerminated
    RefreshTokenFamilyRevoked
    UserSecurityProjectionUpdated
    IdentityProviderLogoutRequested
    UserSessionIndexUpdated
    UserSecurityNotificationRequested

---

## 117. Aucun secret

Les événements ne doivent jamais contenir :

- access token ;
- refresh token ;
- cookie ;
- mot de passe ;
- secret MFA ;
- credential WebAuthn brut.

---

## 118. Notifications

Une notification peut être envoyée lorsque :

- le User demande une déconnexion globale ;
- une compromission est détectée ;
- un administrateur agit ;
- un fournisseur externe impose la révocation.

---

## 119. Notification de sécurité

Elle peut indiquer :

- le nombre de sessions révoquées ;
- la date ;
- le motif général ;
- les actions recommandées.

Elle ne doit jamais exposer :

- les tokens ;
- les secrets ;
- les informations internes de sécurité.

---

## 120. Idempotence

La commande doit être entièrement idempotente.

Une même intention ne doit jamais provoquer une seconde révocation.

---

## 121. Clé d'idempotence

Clé recommandée :

    UserId
    +
    RevokeAllSessionsRequestId

Cette combinaison identifie une intention métier unique.

---

## 122. Empreinte

L'empreinte peut inclure :

    UserId
    RevokedBy
    RevokedAt
    RevocationSource
    RevocationReason
    KeepCurrentSession
    CurrentSessionId
    PolicyId
    SecurityIncidentId
    RiskAssessmentId

---

## 123. Retry identique

Scénario :

    request
        ↓
    commit
        ↓
    response lost
        ↓
    retry

Le système retourne exactement le premier résultat.

Aucune nouvelle modification n'est effectuée.

---

## 124. Nouvelle intention

Même User :

mais nouveau :

    RevokeAllSessionsRequestId

Une nouvelle commande est exécutée.

Les sessions déjà terminales restent inchangées.

---

## 125. IdempotencyConflict

Si un même identifiant représente deux intentions différentes :

    IdempotencyConflict

doit être retournée.

---

## 126. Sessions déjà révoquées

Une session déjà :

    Revoked

n'est jamais révoquée une seconde fois.

Son historique reste inchangé.

---

## 127. Sessions expirées

Une session :

    Expired

reste :

    Expired

La commande ne transforme pas son état.

---

## 128. Zéro Session active

Si aucune session n'est éligible :

le résultat est un succès.

La condition métier est déjà satisfaite.

---

## 129. KeepCurrentSession

Si :

    KeepCurrentSession = true

le résultat doit explicitement indiquer :

    PreservedCurrentSession = true

---

## 130. Concurrence

La commande peut entrer en concurrence avec :

    CreateSession
    RefreshSession
    ElevateSession
    RevokeSession
    ExpireSession

Chaque cas doit être défini.

---

## 131. Concurrence avec CreateSession

Deux possibilités.

### CreateSession avant

La nouvelle session est récupérée par la sélection.

Elle est révoquée.

### CreateSession après

La nouvelle session possède déjà le nouveau :

    UserSecurityVersion

Elle reste valide.

---

## 132. Garantie recherchée

Aucune session utilisant une ancienne version de sécurité ne doit survivre.

---

## 133. Concurrence avec RefreshSession

Si le refresh gagne :

la nouvelle session est créée.

Puis :

    RevokeAllUserSessions

la termine.

Si la révocation gagne :

    RefreshSession

échoue immédiatement.

---

## 134. Concurrence avec RevokeSession

Une session peut déjà être révoquée individuellement.

La commande globale la considère simplement comme :

    AlreadyTerminal

---

## 135. Concurrence avec ElevateSession

Si l'élévation gagne avant :

la révocation termine immédiatement cette élévation.

Si la révocation gagne :

l'élévation échoue.

---

## 136. Concurrence avec ExpireSession

Une seule transition terminale peut gagner.

Le résultat final reste cohérent.

---

## 137. Concurrence avec changement de mot de passe

Le changement de mot de passe peut appeler :

    RevokeAllUserSessions

Les deux workflows doivent rester compatibles.

---

## 138. Concurrence avec MFA

Même principe.

Le remplacement des facteurs peut imposer :

    RevokeAllUserSessions

---

## 139. Atomicité

Le commit logique contient :

    UserSecurityVersion
        +
    every Session revoked
        +
    SessionSecurityVersions
        +
    Refresh families
        +
    Elevations
        +
    Session events
        +
    Global event
        +
    Idempotency

---

## 140. Outbox

L'événement :

    AllUserSessionsRevoked

doit être enregistré dans la même transaction logique.

---

## 141. Publication

La publication des événements intervient uniquement :

    after commit

---

## 142. Source de vérité

La première source de vérité devient :

    UserSecurityVersion

Une ancienne session est immédiatement considérée comme invalide.

---

## 143. Pourquoi cette stratégie

Même si un handler asynchrone est retardé,

le contrôle :

    Session.UserSecurityVersion
        ==
    User.UserSecurityVersion

protège déjà le système.

---

## 144. Audit

L'audit doit permettre de répondre notamment à :

    who revoked every Session

    when

    why

    how many Sessions

    which policy

---

## 145. Informations d'audit

Exemples :

- UserId
- ActorUserId
- RevokedSessionCount
- RevokedAt
- RevocationReason
- RevocationSource
- PolicyId
- UserSecurityVersion
- CorrelationId

---

## 146. Confidentialité

L'audit ne doit jamais conserver :

- access tokens ;
- refresh tokens ;
- cookies ;
- secrets MFA ;
- mots de passe.

---

## 147. États interdits

Les situations suivantes sont interdites :

    Active Session
        survives
    after successful RevokeAllUserSessions

---

    UserSecurityVersion unchanged
    after successful global revocation

---

    Session revoked
    but Refresh family still Active

---

    Active Elevation
    survives global revocation

---

    Global event produced
    but UserSecurityVersion unchanged

---

    SessionRevoked emitted
    while Session remains Active

---

## 148. Effets externes

Après succès,

des handlers peuvent :

- supprimer les cookies ;
- invalider les JWT ;
- vider les caches ;
- mettre à jour les projections ;
- notifier le User ;
- prévenir l'Identity Provider ;
- interrompre les intégrations ;
- fermer les WebSockets ;
- invalider les sessions distribuées.

Ces traitements restent hors du domaine.

---

## 149. Erreurs métier

Les erreurs suivantes peuvent être retournées par la commande.

---

### UserNotFound

Le `User` n'existe pas.

---

### UserRemoved

Le compte est définitivement supprimé et la politique interdit la révocation.

---

### ActorNotAuthorized

L'acteur n'est pas autorisé à révoquer les sessions de ce `User`.

---

### RevokeAllSessionsNotAuthorized

La politique interdit cette opération.

---

### AdministrativePermissionRequired

Une permission administrative est requise.

Exemple :

    identity.sessions.revoke-all

---

### CurrentSessionRequired

Le paramètre :

    KeepCurrentSession = true

nécessite :

    CurrentSessionId

---

### CurrentSessionNotOwnedByUser

La session indiquée ne correspond pas au `User` ciblé.

---

### CurrentSessionAlreadyTerminal

La session devant être conservée est déjà :

    Revoked

ou

    Expired

---

### RevocationPolicyNotFound

La politique demandée n'existe pas.

---

### RevocationPolicyViolation

La politique interdit cette combinaison de paramètres.

---

### RevocationReasonRequired

Le motif est obligatoire.

---

### RevocationSourceInvalid

La source est invalide.

---

### RevocationReasonSourceMismatch

Le motif ne correspond pas à la source.

---

### SecurityIncidentReferenceRequired

La politique exige un incident de sécurité.

---

### RiskAssessmentRequired

Une évaluation de risque est obligatoire.

---

### UserSecurityVersionConflict

Le `User` a été modifié de manière concurrente.

---

### SessionVersionConflict

Une ou plusieurs sessions ont changé pendant le traitement.

---

### SessionSelectionConflict

La liste des sessions éligibles a changé pendant l'exécution.

---

### SessionRevocationConflict

Une session est devenue incompatible avec la révocation.

---

### IdempotencyConflict

Le même identifiant représente une autre intention métier.

---

## 150. Décisions de conception

### Le User exprime l'intention métier

Même si les `Session` restent des agrégats autonomes,

l'intention métier concerne :

    un User

et

    toutes ses Sessions.

---

### Les Sessions restent autonomes

Chaque session :

- possède sa propre identité ;
- conserve son historique ;
- produit ses propres événements ;
- incrémente sa propre version.

---

### UserSecurityVersion protège le compte

L'incrémentation de :

    UserSecurityVersion

est la première protection contre la réutilisation d'anciennes sessions.

---

### SessionSecurityVersion protège chaque Session

Chaque session continue de gérer sa propre sécurité.

Les deux niveaux sont complémentaires.

---

### Les Sessions terminales restent inchangées

Une session déjà :

    Revoked

ou

    Expired

ne change jamais d'état.

---

### Les événements restent spécialisés

Le domaine produit :

    SessionRevoked

pour chaque session,

et

    AllUserSessionsRevoked

pour l'intention globale.

---

### Les Refresh Families suivent les Sessions

Chaque famille appartient à une seule session.

La révocation globale invalide donc chaque famille indépendamment.

---

### Les Élévations suivent les Sessions

Une élévation ne peut jamais survivre à la révocation de sa session.

---

### Aucun secret ne quitte le domaine

Les événements ne contiennent jamais :

- token ;
- cookie ;
- mot de passe ;
- secret MFA ;
- credential brut.

---

### Les autres agrégats restent inchangés

La commande ne modifie jamais :

- User Profile ;
- Membership ;
- Role ;
- Permission ;
- Workspace.

---

## 151. Cas limites

### Une seule Session active

Le résultat est identique à :

    RevokeSession

mais l'intention métier reste différente.

---

### Plusieurs centaines de Sessions

La commande reste valide.

L'implémentation pourra optimiser le traitement,

mais le résultat métier reste identique.

---

### Plusieurs milliers de Sessions

Le traitement peut être :

- séquentiel ;
- parallèle ;
- batché.

Ces choix restent techniques.

---

### Toutes les Sessions déjà révoquées

La commande réussit.

Aucune nouvelle transition n'est produite.

---

### Toutes les Sessions expirées

Même comportement.

La condition métier est déjà satisfaite.

---

### Mélange de Sessions

Exemple :

    Active
    Active
    Revoked
    Expired
    Revoked

Après traitement :

    Revoked
    Revoked
    Revoked
    Expired
    Revoked

---

### KeepCurrentSession

Une seule session reste :

    Active

Toutes les autres deviennent :

    Revoked

---

### Création simultanée d'une nouvelle Session

Si la nouvelle session possède déjà :

    UserSecurityVersion

courante,

elle reste valide.

Sinon,

elle est immédiatement rejetée.

---

### Changement de mot de passe simultané

Les deux workflows doivent converger vers :

    aucune ancienne Session valide

---

### Compromission détectée pendant la révocation

Une nouvelle politique plus stricte peut être appliquée,

mais l'intention métier reste identique.

---

### Fournisseur d'identité indisponible

La révocation locale doit réussir.

La déconnexion distante pourra être rejouée ultérieurement.

---

### Notification impossible

L'échec d'une notification

ne remet jamais en cause

la révocation des sessions.

---

## 152. Checklist de validation

Avant le commit :

    User exists
    User satisfies policy
    Actor is authorized
    Revocation policy is valid
    Revocation reason is valid
    Revocation source is valid
    Source matches reason
    KeepCurrentSession parameters are coherent
    Sessions are loaded
    Eligible Sessions are selected
    UserSecurityVersion can be incremented
    Every Session can transition
    Every Refresh family can be revoked
    Every Elevation can terminate
    Session versions are protected
    User version is protected
    Idempotency is verified
    Events can be persisted
    No secret enters events
    Atomic commit is possible

---

## 153. Checklist après commit

Après succès :

    UserSecurityVersion incremented

    every eligible Session revoked

    every SessionSecurityVersion incremented

    every Refresh family invalidated

    every Elevation terminated

    SessionRevoked emitted

    AllUserSessionsRevoked emitted

    Idempotency recorded

    Audit completed

---

## 154. Résumé métier

`RevokeAllUserSessions` représente une décision globale concernant un `User`.

Elle garantit que le compte ne possède plus aucune session active éligible.

Elle ne modifie jamais :

- le profil ;
- les rôles ;
- les permissions ;
- les memberships.

Elle agit exclusivement sur les contextes d'authentification.

---

## 155. Résumé technique

Après succès :

    User
        ├── UserSecurityVersion++
        ├── Session A → Revoked
        ├── Session B → Revoked
        ├── Session C → Revoked
        ├── Refresh Families → Revoked
        ├── Elevations → Terminated
        └── Events persisted

---

## 156. Garanties

La commande garantit que :

- le User existe ;
- l'acteur est autorisé ;
- toutes les sessions éligibles sont identifiées ;
- chaque session devient terminale ;
- aucune famille de refresh ne reste active ;
- aucune élévation ne subsiste ;
- UserSecurityVersion est incrémentée ;
- SessionSecurityVersion est incrémentée ;
- les événements sont produits ;
- les secrets restent hors des événements ;
- la commande est idempotente ;
- la concurrence est protégée ;
- le commit est atomique.

---

## 157. Relations avec les autres commandes

Cette commande complète le cycle métier des sessions :

    CreateSession
        ↓
    RefreshSession
        ↓
    ElevateSession
        ↓
    RevokeSession
        ↓
    RevokeAllUserSessions
        ↓
    ExpireSession

Chaque commande représente une intention métier distincte.

---

## 158. Synthèse

`RevokeAllUserSessions` est la commande responsable de la révocation globale des contextes d'authentification d'un `User`.

Elle exprime l'intention métier :

    "ce compte ne doit plus disposer
    d'aucune session active"

Pour atteindre cet objectif, elle :

- sélectionne les sessions éligibles ;
- applique la politique de révocation ;
- incrémente `UserSecurityVersion` ;
- révoque individuellement chaque `Session` ;
- invalide chaque famille de refresh credentials ;
- termine toutes les élévations ;
- produit un historique complet grâce aux événements `SessionRevoked` ;
- publie un événement global `AllUserSessionsRevoked` ;
- conserve l'intégralité des informations d'audit ;
- garantit l'idempotence, la cohérence concurrente et l'atomicité de l'opération.

Le résultat final est qu'aucune session appartenant au `User` ne peut plus être utilisée pour s'authentifier, se rafraîchir ou obtenir de nouveaux privilèges, tout en préservant l'historique et sans modifier les autres agrégats métier.
