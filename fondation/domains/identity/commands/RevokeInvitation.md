---
id: IDN-CMD-REVOKE-INVITATION
title: RevokeInvitation
status: Draft
owner: Product
version: 1.0.0
last_updated: 2026-07-30

aggregate: Invitation

references:
  - README.md
  - ../entities.md
  - ../aggregates.md
  - ../relationships.md
  - ../invariants.md
  - ../permissions.md
  - ../events/InvitationRevoked.md
  - CreateInvitation.md
  - SendInvitation.md
  - ResendInvitation.md
  - AcceptInvitation.md
  - DeclineInvitation.md
  - ExpireInvitation.md
---

# RevokeInvitation

## Objectif

La commande `RevokeInvitation` retire explicitement une `Invitation` encore active.

Elle permet à un acteur autorisé de déclarer que l’autorisation temporaire accordée au destinataire ne doit plus pouvoir être utilisée.

Après révocation, l’`Invitation` ne peut plus être :

- acceptée ;
- envoyée ;
- renvoyée ;
- prolongée ;
- réactivée ;
- utilisée pour créer ou restaurer un `Membership`.

---

## Agrégat concerné

`Invitation`

L’`Invitation` constitue la racine de l’agrégat modifié.

Aucun `Membership` existant n’est modifié par cette commande.

---

## Acteur

La commande peut être demandée par :

- le `User` ayant créé l’`Invitation` ;
- un autre `User` autorisé à gérer les invitations du `Workspace` ;
- un propriétaire ou administrateur selon la politique d’autorisation ;
- un processus système explicitement autorisé ;
- un workflow de sécurité ;
- un workflow de fermeture du `Workspace`.

L’acteur doit être identifié ou représenté par un `SystemActor` explicite.

---

## Permission requise

La permission recommandée est :

```text
workspace.members.invite
```

ou, selon la granularité retenue :

```text
workspace.members.manage
```

Une permission plus spécifique peut être introduite :

```text
workspace.invitations.revoke
```

La permission doit être évaluée dans le `Workspace` de l’`Invitation`.

---

## Préconditions

Avant l’exécution de `RevokeInvitation`, les conditions suivantes doivent être satisfaites :

- l’`Invitation` existe ;
- son état est `Pending` ;
- elle n’a pas été acceptée ;
- elle n’a pas été refusée ;
- elle n’a pas expiré ;
- elle n’a pas déjà été révoquée, sauf reprise idempotente ;
- l’acteur est autorisé dans le `Workspace` ;
- aucune acceptation finalisée n’a créé ou restauré un `Membership` ;
- la demande respecte les règles de concurrence ;
- le motif de révocation est valide lorsqu’il est requis.

Une invitation temporellement expirée ne doit pas être révoquée comme si elle était encore active.

Lorsque :

```text
RevokedAt >= Invitation.ExpirationDate
```

l’état métier attendu est `Expired`.

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `InvitationId` | `InvitationId` | Oui | Identifie l’`Invitation` à révoquer. |
| `RevokedBy` | `UserId` ou `SystemActor` | Oui | Identifie l’origine de la révocation. |
| `RevokedAt` | Instant | Oui | Instant de référence de la révocation. |
| `RevocationRequestId` | Identifiant | Oui | Identifie la demande de manière idempotente. |
| `Reason` | `InvitationRevocationReason` | Oui | Motif structuré de la révocation. |

Données facultatives :

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `Comment` | Texte court | Non | Précision interne facultative. |
| `CorrelationId` | Identifiant | Non | Référence du workflow appelant. |
| `SecurityContext` | Données structurées | Non | Contexte utile pour une révocation de sécurité. |

---

## InvitationRevocationReason

Le motif peut prendre des valeurs telles que :

```text
InvitedByMistake
WrongRecipient
WrongRole
AccessNoLongerNeeded
WorkspacePolicyChanged
RoleUnavailable
WorkspaceClosing
SecurityConcern
DuplicateInvitation
AdministrativeDecision
Other
```

Le motif doit exprimer la raison fonctionnelle de la révocation.

Il ne doit pas servir à contourner les règles d’autorisation.

---

## Validation des données

### InvitationId

L’`InvitationId` doit identifier une `Invitation` existante.

Il reste distinct de l’`InvitationToken`.

---

### RevokedBy

Lorsque l’acteur est un `User`, celui-ci doit :

- exister ;
- être actif ;
- posséder un `Membership` valide dans le `Workspace` ;
- posséder la `Permission` requise ;
- être autorisé à révoquer une invitation portant sur le `Role` prévu.

Lorsque l’acteur est le système, son origine doit être explicitement autorisée.

---

### RevokedAt

`RevokedAt` est utilisé pour :

- évaluer l’expiration ;
- ordonner les transitions concurrentes ;
- enregistrer l’audit ;
- dater l’invalidation du token.

La condition suivante doit être vraie :

```text
RevokedAt < Invitation.ExpirationDate
```

Lorsque cette condition est fausse, l’`Invitation` doit être traitée comme expirée.

---

### RevocationRequestId

Le `RevocationRequestId` doit :

- identifier une intention logique unique ;
- rester stable lors des retries techniques ;
- permettre de restituer un résultat déjà obtenu ;
- ne contenir aucune donnée sensible.

---

### Reason

Le motif doit appartenir au catalogue défini.

Certains motifs peuvent imposer des contrôles supplémentaires.

Exemple :

```text
SecurityConcern
```

peut nécessiter :

- un niveau d’autorisation supérieur ;
- un audit renforcé ;
- une notification de sécurité ;
- une invalidation immédiate des messages en cours de traitement lorsque possible.

---

### Comment

Le commentaire doit :

- respecter une longueur maximale ;
- être traité comme une donnée utilisateur non fiable ;
- ne contenir aucun secret ;
- être protégé contre les injections ;
- respecter la politique de conservation.

---

## Traitement métier

Le traitement suit les étapes conceptuelles suivantes.

### 1. Charger l’Invitation

Le système charge l’`Invitation` identifiée par `InvitationId`.

Si elle n’existe pas, la commande échoue.

---

### 2. Vérifier l’état

L’`Invitation` doit être dans l’état :

```text
Pending
```

Les états suivants interdisent une nouvelle révocation :

```text
Accepted
Declined
Expired
```

L’état `Revoked` déclenche le traitement idempotent.

---

### 3. Vérifier l’expiration

La révocation est évaluée à partir de l’instant métier fourni.

Lorsque :

```text
RevokedAt >= Invitation.ExpirationDate
```

l’`Invitation` est déjà expirée par le temps.

La commande doit alors :

- échouer avec `InvitationExpired` ;
- ou coordonner l’enregistrement de l’expiration avant de retourner le résultat approprié.

Elle ne doit pas masquer une expiration sous une révocation tardive.

---

### 4. Vérifier le Workspace

Le système vérifie que le `Workspace` existe encore suffisamment pour évaluer l’autorisation.

Dans certains scénarios de fermeture, un processus système peut révoquer les invitations restantes même si le `Workspace` n’accepte plus de nouvelles opérations ordinaires.

Cette capacité doit être explicitement prévue.

---

### 5. Autoriser l’acteur

L’autorisation doit être évaluée dans le contexte exact :

```text
UserId
WorkspaceId
PermissionKey
RoleId
```

Le droit général de gérer des membres ne signifie pas nécessairement le droit de révoquer une invitation destinée à un rôle supérieur.

Exemple :

- un `Admin` peut révoquer une invitation vers `Member` ;
- un `Admin` ne peut pas nécessairement révoquer une invitation vers `Owner` ;
- un workflow système de sécurité peut révoquer toute invitation selon une politique dédiée.

---

### 6. Vérifier l’absence d’acceptation finalisée

Le système vérifie qu’aucun `Membership` n’a été créé ou restauré à partir de cette `Invitation`.

Lorsque l’acceptation est déjà finalisée, la révocation ne peut plus retirer l’accès.

Une commande sur `Membership` est alors nécessaire.

---

### 7. Vérifier les opérations en cours

Le système vérifie notamment :

- qu’aucune acceptation n’a déjà gagné ;
- qu’aucune autre transition terminale n’est persistée ;
- qu’une demande de renvoi en cours ne puisse pas rendre le token réutilisable ;
- que la version de l’agrégat est toujours celle attendue.

---

### 8. Enregistrer la révocation

L’agrégat passe de :

```text
Pending
```

à :

```text
Revoked
```

Il enregistre notamment :

- `RevokedBy` ;
- `RevokedAt` ;
- `Reason` ;
- `Comment`, lorsque présent ;
- `RevocationRequestId`.

---

### 9. Invalider le token

Le `InvitationToken` devient immédiatement et définitivement inutilisable.

Cette invalidation doit se produire dans la même unité atomique que la transition vers `Revoked`.

---

### 10. Annuler les traitements futurs

Après révocation, les traitements suivants doivent devenir inopérants :

- relances automatiques ;
- envois différés non encore exécutés ;
- acceptation du lien ;
- notifications de rappel ;
- renouvellement du token ;
- workflows d’onboarding associés.

L’annulation technique peut être asynchrone, mais chaque consommateur doit réévaluer l’état de l’`Invitation` avant d’agir.

---

### 11. Produire l’événement

L’agrégat produit :

```text
InvitationRevoked
```

Les notifications et compensations techniques sont déclenchées séparément.

---

## Résultat attendu

Après une exécution réussie :

- l’`Invitation` est `Revoked` ;
- l’acteur et le motif sont enregistrés ;
- le token est inutilisable ;
- aucune acceptation future n’est possible ;
- aucune nouvelle transmission ne doit être initiée ;
- aucun `Membership` n’est créé, supprimé ou suspendu ;
- les retries retournent le résultat initial sans nouvel effet.

État conceptuel :

```text
Invitation
├── Status: Revoked
├── RevokedBy
├── RevokedAt
├── RevocationReason
├── RevocationRequestId
└── Token: unusable
```

---

## Invariants concernés

### `IDN-INV-007`

Une `Invitation` révoquée ne peut produire aucun `Membership`.

---

### `IDN-INV-008`

La révocation ne modifie ni le destinataire ni l’identité attachée à l’`Invitation`.

---

### `IDN-INV-009`

`Revoked` est un état terminal.

L’`Invitation` ne peut pas revenir à `Pending`.

---

### `IDN-INV-012`

Le token reste distinct de l’identifiant et devient inutilisable après la révocation.

---

### `IDN-INV-014`

L’autorisation de l’acteur est évaluée dans le `Workspace` de l’`Invitation`.

---

### `IDN-INV-015`

La référence au `Workspace` doit rester suffisamment valide pour appliquer la politique de révocation.

---

## Transition d’état

Transition autorisée :

```text
Pending
    ↓
Revoked
```

Transitions interdites :

```text
Accepted
    ↓
Revoked
```

```text
Declined
    ↓
Revoked
```

```text
Expired
    ↓
Revoked
```

```text
Revoked
    ↓
Pending
```

---

## Événement produit

### InvitationRevoked

La commande produit :

```text
InvitationRevoked
```

L’événement peut contenir :

- `InvitationId`
- `WorkspaceId`
- `RoleId`
- `RevokedBy`
- `RevokedAt`
- `Reason`
- `RevocationRequestId`
- `CorrelationId`

Il ne doit pas contenir :

- l’`InvitationToken` brut ;
- le lien d’acceptation ;
- un secret de session ;
- un commentaire libre non nécessaire aux consommateurs ;
- des informations internes sans lien avec la révocation.

---

## Événements non produits

Cette commande ne produit pas :

```text
InvitationDeclined
InvitationExpired
InvitationAccepted
MembershipRemoved
MembershipSuspended
```

Elle ne retire aucun accès déjà accordé.

---

## Erreurs métier

### InvitationNotFound

L’`Invitation` n’existe pas.

---

### InvitationAlreadyAccepted

L’`Invitation` a déjà été acceptée.

L’accès éventuel doit être géré par une commande sur `Membership`.

---

### InvitationDeclined

Le destinataire a déjà refusé l’`Invitation`.

---

### InvitationExpired

L’`Invitation` est expirée temporellement ou persistée comme telle.

---

### InvitationAlreadyRevoked

L’`Invitation` a déjà été révoquée.

Cette erreur ne doit pas être retournée lorsqu’il s’agit d’une reprise idempotente de la même demande.

---

### ActorNotAuthorized

L’acteur ne possède pas la permission requise dans le `Workspace`.

---

### RoleRevocationNotAllowed

L’acteur ne peut pas révoquer une invitation portant sur ce `Role`.

---

### WorkspaceNotFound

Le `Workspace` n’existe plus et aucun processus système autorisé ne permet de traiter la révocation.

---

### MembershipAlreadyCreated

Un `Membership` a déjà été créé ou restauré à partir de l’`Invitation`.

---

### RevocationConflict

Une transition concurrente a gagné avant la révocation.

---

### InvalidRevocationReason

Le motif fourni n’est pas reconnu.

---

### RevocationAlreadyInProgress

Une demande équivalente est en cours.

Cette erreur est optionnelle si le système préfère retourner le résultat idempotent.

---

## Idempotence

`RevokeInvitation` doit être idempotente pour :

```text
InvitationId + RevocationRequestId
```

La répétition de la même commande doit retourner le résultat initial sans :

- modifier `RevokedAt` ;
- remplacer le motif initial ;
- produire un nouvel événement métier ;
- invalider une seconde fois le token ;
- déclencher plusieurs notifications fonctionnelles ;
- répéter des compensations non idempotentes.

---

## Reprise après réponse perdue

Cas typique :

```text
RevokeInvitation succeeds

↓

Invitation becomes Revoked

↓

Response is lost

↓

Client retries
```

La seconde exécution doit retrouver :

- l’état `Revoked` ;
- le même `RevocationRequestId` ;
- le même instant de révocation ;
- le même motif.

Elle retourne alors le succès initial.

---

## Révocation déjà réalisée par une autre demande

Lorsque l’`Invitation` est déjà `Revoked` avec un autre `RevocationRequestId`, aucun nouvel effet métier ne doit être produit.

Le système peut retourner :

```text
InvitationAlreadyRevoked
```

ou un résultat générique indiquant que l’Invitation n’est plus active.

---

## Concurrence

### RevokeInvitation contre AcceptInvitation

Une seule transition terminale doit réussir.

Résultats possibles :

```text
Accepted
```

ou :

```text
Revoked
```

Jamais les deux.

Une révocation ne peut pas annuler rétroactivement une acceptation déjà finalisée.

---

### RevokeInvitation contre DeclineInvitation

Une seule transition terminale est persistée.

Le résultat peut être :

```text
Declined
```

ou :

```text
Revoked
```

selon l’opération validée en premier.

---

### RevokeInvitation contre ExpireInvitation

L’instant métier détermine le résultat.

Si :

```text
RevokedAt < ExpirationDate
```

la révocation peut réussir.

Si :

```text
RevokedAt >= ExpirationDate
```

le résultat correct est `Expired`.

---

### RevokeInvitation contre ResendInvitation

Si la révocation gagne :

- la relance doit être abandonnée ;
- aucun message futur ne doit contenir un lien utilisable ;
- le consommateur doit vérifier l’état avant l’envoi.

Si la relance a déjà été confiée au fournisseur :

- le message peut encore être distribué ;
- le token contenu dans le lien doit néanmoins être inutilisable ;
- aucune acceptation ne doit réussir.

---

### Deux révocations concurrentes

Deux commandes avec le même `RevocationRequestId` représentent la même intention et doivent être dédupliquées.

Deux commandes avec des identifiants différents ne doivent produire qu’une seule transition :

```text
Pending -> Revoked
```

---

## Atomicité

La transition suivante doit être atomique :

```text
verify Pending
+
record Revoked
+
invalidate token
+
record domain event
```

L’état suivant est interdit :

```text
Status = Revoked
AND
token remains usable
```

La publication externe peut être différée via une outbox transactionnelle.

---

## Relation avec DeclineInvitation

Les deux commandes ferment l’`Invitation`, mais elles n’expriment pas la même décision.

### DeclineInvitation

```text
Actor: Recipient
Meaning: I do not want to join
```

### RevokeInvitation

```text
Actor: Authorized administrator or system
Meaning: This invitation is no longer authorized
```

La distinction est nécessaire pour :

- l’audit ;
- les notifications ;
- les métriques ;
- l’expérience utilisateur ;
- l’analyse des motifs ;
- les politiques de sécurité.

---

## Relation avec Membership

`RevokeInvitation` ne retire aucun `Membership`.

Lorsqu’une invitation a déjà été acceptée, l’accès doit être géré par une commande telle que :

```text
SuspendMembership
RemoveMembership
ChangeRole
```

Une révocation ne doit jamais contourner les règles propres au cycle de vie de `Membership`.

---

## Relation avec SendInvitation

Une révocation réussie interdit toute nouvelle commande :

```text
SendInvitation
ResendInvitation
```

Les demandes déjà en file doivent être considérées comme obsolètes.

Chaque consommateur doit vérifier au minimum :

```text
Invitation.Status = Pending
```

avant de construire ou transmettre le message.

---

## Relation avec ExpireInvitation

`RevokeInvitation` résulte d’une décision explicite.

`ExpireInvitation` résulte du temps.

```text
Revoked
    -> authorization withdrawn explicitly

Expired
    -> authorization ended automatically
```

Ces faits doivent rester distincts.

---

## Révocation automatique

Un processus système peut révoquer une invitation pour des raisons telles que :

- fermeture du `Workspace` ;
- désactivation du `Role` ;
- détection d’un risque ;
- suppression du domaine e-mail autorisé ;
- changement de politique ;
- remplacement par une nouvelle invitation ;
- dépassement d’une règle de sécurité.

Le processus doit :

- être explicitement identifié ;
- fournir un motif structuré ;
- respecter l’idempotence ;
- produire le même événement métier ;
- ne pas modifier l’historique de manière silencieuse.

---

## Révocation en masse

Une opération de fermeture ou de sécurité peut nécessiter la révocation de plusieurs invitations.

Le traitement en masse doit être coordonné par un workflow.

Chaque `Invitation` doit néanmoins :

- être chargée individuellement ;
- valider sa transition ;
- produire son propre `InvitationRevoked` ;
- conserver son audit ;
- rester idempotente.

Une commande de masse ne doit pas contourner l’agrégat avec une mise à jour directe indiscriminée.

---

## Notifications

Après `InvitationRevoked`, un handler peut :

- informer le destinataire ;
- informer l’invitant ;
- arrêter les rappels ;
- invalider une projection ;
- enregistrer une alerte de sécurité ;
- prévenir le support.

La notification ne fait pas partie du succès transactionnel principal.

Un échec d’envoi ne doit pas annuler la révocation.

---

## Visibilité du motif

Le produit doit définir quels motifs sont visibles par :

- le destinataire ;
- l’invitant ;
- les administrateurs ;
- le support ;
- les équipes de sécurité.

Exemples :

- `WrongRecipient` peut être présenté au destinataire ;
- `SecurityConcern` peut rester restreint ;
- un commentaire interne ne doit pas être exposé par défaut.

---

## Sécurité

La commande doit garantir que :

- le token est invalidé immédiatement ;
- aucun secret n’est journalisé ;
- l’autorisation est contextualisée par `Workspace` ;
- les acteurs ne peuvent pas révoquer des invitations hors de leur périmètre ;
- les restrictions liées au `Role` sont respectées ;
- une révocation concurrente avec l’acceptation ne crée pas d’état incohérent ;
- les messages déjà envoyés ne permettent plus d’obtenir un accès ;
- les traitements différés réévaluent l’état courant.

---

## Confidentialité

Le résultat public ne doit pas révéler inutilement :

- l’existence d’un compte pour le destinataire ;
- les autres invitations ;
- la structure des rôles ;
- les permissions de l’acteur ;
- les raisons de sécurité internes.

Seules les informations nécessaires à la compréhension de la révocation doivent être exposées.

---

## Audit

Une révocation réussie doit enregistrer :

- `InvitationId`
- `WorkspaceId`
- `RoleId`
- `RevokedBy`
- `RevokedAt`
- `Reason`
- `RevocationRequestId`
- origine du processus
- contexte de sécurité minimisé
- résultat

L’audit ne doit jamais contenir :

- le token brut ;
- le lien complet ;
- des secrets d’authentification ;
- des informations personnelles non nécessaires.

---

## Décisions de conception

### La révocation est terminale

Une `Invitation` révoquée ne peut pas revenir à `Pending`.

Pour inviter de nouveau la personne, une nouvelle `Invitation` doit être créée.

---

### La révocation ne retire pas un accès existant

Une `Invitation` est une autorisation temporaire.

Une fois transformée en `Membership`, son rôle est terminé.

Le retrait d’un accès relève donc exclusivement du cycle de vie du `Membership`.

---

### Le motif est obligatoire

Contrairement au refus du destinataire, la révocation est une action administrative ou système.

Elle doit être justifiée par un motif structuré afin de garantir une traçabilité suffisante.

---

### Le token est invalidé atomiquement

La révocation n’est complète que lorsque le secret ne peut plus être utilisé.

L’invalidation fait partie de la transition métier.

---

### Les messages déjà envoyés peuvent subsister

Le domaine ne peut pas supprimer un e-mail déjà distribué.

Il garantit en revanche que le lien qu’il contient ne permet plus d’accepter l’`Invitation`.

---

### Les traitements différés doivent revalider l’état

Une demande d’envoi créée avant la révocation ne constitue pas une autorisation permanente.

Le consommateur doit vérifier que l’`Invitation` est encore `Pending` avant tout effet externe.

---

### La révocation conserve l’entité

L’`Invitation` n’est pas supprimée.

Elle est conservée avec son état terminal afin de permettre :

- l’audit ;
- l’idempotence ;
- la prévention des rejeux ;
- l’analyse des décisions ;
- la compréhension du parcours.

---

## Synthèse

`RevokeInvitation` retire explicitement une autorisation temporaire encore active.

Elle garantit que :

- l’`Invitation` est toujours `Pending` ;
- l’acteur est autorisé dans le bon `Workspace` ;
- le motif est explicite et auditable ;
- une seule transition terminale est appliquée ;
- le token devient immédiatement inutilisable ;
- les envois et relances futurs sont bloqués ;
- aucun `Membership` existant n’est affecté ;
- les retries et traitements concurrents ne produisent aucun doublon.

Le résultat final est :

```text
Pending Invitation
    ↓
Revoked Invitation
```

avec un retrait d’autorisation explicite, terminal et traçable.