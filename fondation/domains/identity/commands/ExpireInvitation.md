---
id: IDN-CMD-EXPIRE-INVITATION
title: ExpireInvitation
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
  - ../workflows.md
  - ../events/InvitationExpired.md
  - CreateInvitation.md
  - SendInvitation.md
  - ResendInvitation.md
  - AcceptInvitation.md
  - DeclineInvitation.md
  - RevokeInvitation.md
---

# ExpireInvitation

## Objectif

La commande `ExpireInvitation` fait passer une `Invitation` active à l’état `Expired` lorsque son échéance est atteinte.

Elle matérialise dans le modèle un fait déjà déterminé par le temps :

```text
CurrentTime >= Invitation.ExpirationDate
```

Après expiration, l’`Invitation` ne peut plus être :

- acceptée ;
- refusée ;
- révoquée comme invitation active ;
- envoyée ;
- renvoyée ;
- prolongée implicitement ;
- utilisée pour créer ou restaurer un `Membership`.

---

## Agrégat concerné

`Invitation`

L’`Invitation` constitue la racine de l’agrégat modifié.

Aucun autre agrégat n’est directement modifié.

---

## Acteur

La commande est généralement déclenchée par :

- un scheduler ;
- un worker ;
- un processus de maintenance ;
- une vérification effectuée pendant une autre commande ;
- un workflow de fermeture ou de nettoyage.

L’acteur recommandé est :

```text
System
```

Une exécution manuelle par un opérateur peut être autorisée pour des besoins de reprise ou d’administration, mais elle ne doit pas permettre de contourner la condition temporelle.

---

## Permission requise

Aucune `Permission` utilisateur ordinaire n’est requise lorsque la commande est exécutée par un processus système autorisé.

Une exécution administrative manuelle peut nécessiter une permission technique telle que :

```text
identity.invitations.expire
```

Cette permission ne permet pas d’expirer une invitation avant son échéance.

---

## Préconditions

Avant l’exécution de `ExpireInvitation`, les conditions suivantes doivent être satisfaites :

- l’`Invitation` existe ;
- son état est `Pending` ;
- son échéance est atteinte ou dépassée ;
- elle n’a pas été acceptée ;
- elle n’a pas été refusée ;
- elle n’a pas été révoquée ;
- elle n’est pas déjà expirée, sauf reprise idempotente ;
- aucune transition terminale concurrente n’a déjà gagné.

La condition temporelle centrale est :

```text
ExpiredAt >= Invitation.ExpirationDate
```

---

## Données d’entrée

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `InvitationId` | `InvitationId` | Oui | Identifie l’`Invitation` à expirer. |
| `ExpiredAt` | Instant | Oui | Instant utilisé pour évaluer l’échéance. |
| `ExpirationRequestId` | Identifiant | Oui | Identifie la demande de manière idempotente. |
| `TriggeredBy` | `SystemActor` ou `UserId` | Oui | Identifie l’origine de l’exécution. |

Données facultatives :

| Donnée | Type | Obligatoire | Description |
|--------|------|-------------|-------------|
| `CorrelationId` | Identifiant | Non | Relie la commande au traitement appelant. |
| `ExecutionSource` | `ExpirationExecutionSource` | Non | Indique comment l’expiration a été détectée. |

---

## ExpirationExecutionSource

La source peut notamment prendre les valeurs suivantes :

```text
ScheduledSweep
LazyEvaluation
ManualRecovery
WorkflowCheck
SecurityProcess
```

### ScheduledSweep

L’expiration est détectée par un traitement planifié.

### LazyEvaluation

L’expiration est constatée pendant une autre opération, par exemple :

```text
AcceptInvitation
SendInvitation
ResendInvitation
DeclineInvitation
RevokeInvitation
```

### ManualRecovery

Un opérateur relance explicitement un traitement après incident.

### WorkflowCheck

Un workflow métier détecte que l’`Invitation` n’est plus temporellement valide.

### SecurityProcess

Un processus de sécurité force la réévaluation des invitations arrivées à échéance.

---

## Validation des données

### InvitationId

L’`InvitationId` doit identifier une `Invitation` existante.

Il reste distinct de l’`InvitationToken`.

---

### ExpiredAt

`ExpiredAt` représente l’instant métier de constatation.

La condition suivante doit être vraie :

```text
ExpiredAt >= Invitation.ExpirationDate
```

Le système ne doit pas remplacer arbitrairement l’échéance par l’heure d’exécution du scheduler dans l’historique métier.

Il convient de distinguer :

```text
ExpirationDate
```

et :

```text
ExpiredAt
```

`ExpirationDate` représente l’instant à partir duquel l’`Invitation` n’est plus utilisable.

`ExpiredAt` représente l’instant auquel cette situation a été persistée ou constatée.

---

### ExpirationRequestId

Le `ExpirationRequestId` doit :

- identifier une intention logique unique ;
- rester stable lors des retries techniques ;
- permettre de retrouver un résultat existant ;
- ne contenir aucune donnée sensible.

---

### TriggeredBy

La source doit être autorisée à demander l’expiration.

Elle ne peut pas modifier la règle temporelle.

---

## Traitement métier

Le traitement suit les étapes conceptuelles suivantes.

### 1. Charger l’Invitation

Le système charge l’`Invitation` identifiée par `InvitationId`.

Si elle n’existe pas, la commande échoue.

---

### 2. Vérifier son état

L’`Invitation` doit être dans l’état :

```text
Pending
```

Les états suivants sont déjà terminaux :

```text
Accepted
Declined
Revoked
Expired
```

L’état `Expired` déclenche le comportement idempotent.

---

### 3. Évaluer l’échéance

Le système compare l’instant fourni à l’échéance :

```text
ExpiredAt >= Invitation.ExpirationDate
```

Si cette condition est fausse, la commande échoue avec :

```text
InvitationNotYetExpired
```

L’expiration ne peut pas être anticipée.

Une décision explicite de retrait avant échéance relève de :

```text
RevokeInvitation
```

---

### 4. Vérifier les transitions concurrentes

Le système vérifie qu’aucune autre transition terminale n’a déjà été validée.

Il doit notamment gérer la concurrence avec :

- `AcceptInvitation` ;
- `DeclineInvitation` ;
- `RevokeInvitation`.

Une seule transition terminale peut gagner.

---

### 5. Enregistrer l’expiration

L’agrégat passe de :

```text
Pending
```

à :

```text
Expired
```

Il enregistre notamment :

- `ExpirationDate` ;
- `ExpiredAt` ;
- `TriggeredBy` ;
- `ExecutionSource` ;
- `ExpirationRequestId`.

---

### 6. Invalider le token

Le `InvitationToken` devient définitivement inutilisable.

L’invalidation doit être atomique avec la transition vers `Expired`.

---

### 7. Arrêter les traitements futurs

Les traitements suivants doivent devenir inopérants :

- envois différés ;
- relances automatiques ;
- rappels ;
- acceptation ;
- refus ;
- rotation du token ;
- workflows d’onboarding non finalisés.

Chaque consommateur doit revalider l’état courant avant de produire un effet externe.

---

### 8. Produire l’événement

L’agrégat produit :

```text
InvitationExpired
```

Les traitements de nettoyage, de notification ou de projection sont déclenchés séparément.

---

## Résultat attendu

Après une exécution réussie :

- l’`Invitation` est `Expired` ;
- son échéance reste inchangée ;
- l’instant de constatation est enregistré ;
- le token est inutilisable ;
- aucune acceptation future n’est possible ;
- aucun `Membership` n’est créé ou modifié ;
- les relances futures sont bloquées ;
- les retries ne produisent aucun effet supplémentaire.

État conceptuel :

```text
Invitation
├── Status: Expired
├── ExpirationDate
├── ExpiredAt
├── ExpirationRequestId
├── TriggeredBy
└── Token: unusable
```

---

## Invariants concernés

### `IDN-INV-007`

Une `Invitation` expirée ne peut produire aucun `Membership`.

---

### `IDN-INV-009`

`Expired` est un état terminal.

L’`Invitation` ne peut pas revenir à `Pending`.

---

### `IDN-INV-012`

Le token reste distinct de l’identifiant et devient inutilisable après expiration.

---

### `IDN-INV-015`

L’expiration ne dépend pas de la disponibilité fonctionnelle du `Workspace`.

Même si le `Workspace` est indisponible, le temps continue de s’écouler et l’`Invitation` peut expirer.

---

## Transition d’état

Transition autorisée :

```text
Pending
    ↓
Expired
```

Transitions interdites :

```text
Accepted
    ↓
Expired
```

```text
Declined
    ↓
Expired
```

```text
Revoked
    ↓
Expired
```

```text
Expired
    ↓
Pending
```

---

## Événement produit

### InvitationExpired

La commande produit :

```text
InvitationExpired
```

L’événement peut contenir :

- `InvitationId`
- `WorkspaceId`
- `RoleId`
- `ExpirationDate`
- `ExpiredAt`
- `TriggeredBy`
- `ExecutionSource`
- `ExpirationRequestId`
- `CorrelationId`

Il ne doit pas contenir :

- l’`InvitationToken` brut ;
- le lien d’acceptation ;
- un secret d’infrastructure ;
- des données personnelles non nécessaires.

---

## Événements non produits

Cette commande ne produit pas :

```text
InvitationRevoked
InvitationDeclined
InvitationAccepted
MembershipCreated
MembershipRemoved
```

L’expiration n’est ni un refus du destinataire, ni une décision administrative.

---

## Erreurs métier

### InvitationNotFound

L’`Invitation` n’existe pas.

---

### InvitationNotYetExpired

L’échéance n’est pas encore atteinte.

La condition suivante est encore vraie :

```text
ExpiredAt < Invitation.ExpirationDate
```

---

### InvitationAlreadyAccepted

L’`Invitation` a déjà été acceptée.

---

### InvitationDeclined

L’`Invitation` a déjà été refusée.

---

### InvitationRevoked

L’`Invitation` a déjà été révoquée.

---

### InvitationAlreadyExpired

L’`Invitation` est déjà expirée.

Cette erreur ne doit pas être retournée lorsqu’il s’agit d’une reprise idempotente de la même demande.

---

### ExpirationConflict

Une autre transition terminale a gagné pendant l’exécution.

---

### InvalidExpirationRequest

Les données de la demande sont incohérentes ou insuffisantes.

---

### ExpirationSourceNotAuthorized

Le processus demandeur n’est pas autorisé à exécuter cette commande.

---

## Idempotence

`ExpireInvitation` doit être idempotente pour :

```text
InvitationId + ExpirationRequestId
```

La répétition de la même commande doit retourner le résultat initial sans :

- modifier `ExpiredAt` ;
- produire un nouvel événement métier ;
- invalider une nouvelle fois le token ;
- répéter des notifications fonctionnelles ;
- modifier l’échéance initiale.

---

## Identifiant déterministe pour un scheduler

Un scheduler peut construire un identifiant déterministe à partir de :

```text
InvitationId + ExpirationDate
```

Exemple conceptuel :

```text
ExpirationRequestId =
hash(InvitationId, ExpirationDate)
```

Cette stratégie permet de dédupliquer :

- plusieurs passages du scheduler ;
- une reprise après incident ;
- plusieurs workers traitant la même invitation ;
- une rediffusion du même message technique.

Le hash ne doit pas contenir ni exposer le token.

---

## Reprise après réponse perdue

Cas typique :

```text
ExpireInvitation succeeds

↓

Invitation becomes Expired

↓

Response is lost

↓

Worker retries
```

La seconde exécution retrouve :

- l’état `Expired` ;
- le même `ExpirationRequestId` ;
- le même `ExpiredAt` ou résultat métier initial.

Elle retourne alors le succès existant.

---

## Concurrence

### ExpireInvitation contre AcceptInvitation

Le résultat dépend de l’instant métier.

Lorsque :

```text
AcceptedAt < ExpirationDate
```

une acceptation peut réussir si elle obtient la transition avant l’expiration persistée et si toutes les préconditions sont satisfaites.

Lorsque :

```text
AcceptedAt >= ExpirationDate
```

l’acceptation doit échouer, même si l’état persistant est encore `Pending`.

L’état final doit être :

```text
Expired
```

et non `Accepted`.

---

### ExpireInvitation contre DeclineInvitation

Lorsque :

```text
DeclinedAt < ExpirationDate
```

le refus peut réussir.

Lorsque :

```text
DeclinedAt >= ExpirationDate
```

l’état correct est `Expired`.

---

### ExpireInvitation contre RevokeInvitation

Lorsque :

```text
RevokedAt < ExpirationDate
```

la révocation peut réussir.

Lorsque :

```text
RevokedAt >= ExpirationDate
```

l’état correct est `Expired`.

---

### ExpireInvitation contre SendInvitation

Une invitation arrivée à échéance ne peut plus être envoyée.

Le consommateur de `InvitationSendRequested` doit vérifier :

```text
CurrentTime < ExpirationDate
AND
Status = Pending
```

avant toute transmission.

---

### ExpireInvitation contre ResendInvitation

Une relance initiée avant l’échéance mais exécutée après celle-ci doit être abandonnée.

La demande antérieure ne fige pas la validité de l’`Invitation`.

---

### Deux expirations concurrentes

Deux commandes portant le même `ExpirationRequestId` représentent la même intention.

Elles doivent être dédupliquées.

Deux identifiants différents ne doivent malgré tout produire qu’une seule transition :

```text
Pending -> Expired
```

---

## Atomicité

La transition suivante doit être atomique :

```text
verify Pending
+
verify expiration reached
+
record Expired
+
invalidate token
+
record domain event
```

L’état suivant est interdit :

```text
Status = Expired
AND
token remains usable
```

La publication externe peut être réalisée par une outbox transactionnelle.

---

## Expiration logique et expiration persistée

Une distinction importante existe entre :

```text
logically expired
```

et :

```text
persisted as Expired
```

Une `Invitation` est logiquement expirée dès que :

```text
CurrentTime >= ExpirationDate
```

Même si son champ `Status` contient encore `Pending`, elle ne doit plus être considérée comme utilisable.

La commande `ExpireInvitation` aligne l’état persistant avec cette réalité métier.

---

## Conséquence pour les autres commandes

Toutes les commandes sensibles doivent vérifier l’échéance indépendamment du scheduler.

Elles ne doivent jamais utiliser uniquement :

```text
Invitation.Status = Pending
```

La condition minimale est :

```text
Invitation.Status = Pending
AND
CurrentTime < Invitation.ExpirationDate
```

Cette règle concerne notamment :

- `SendInvitation` ;
- `ResendInvitation` ;
- `AcceptInvitation` ;
- `DeclineInvitation` ;
- `RevokeInvitation`.

---

## Scheduler

Le scheduler constitue un mécanisme de maintenance et non une garantie d’exactitude métier.

Son rôle est de :

- rechercher les invitations encore persistées comme `Pending` ;
- identifier celles dont l’échéance est atteinte ;
- déclencher `ExpireInvitation` ;
- reprendre les traitements échoués ;
- limiter la taille des lots ;
- éviter les traitements concurrents inutiles.

Requête conceptuelle :

```text
Status = Pending
AND
ExpirationDate <= CurrentTime
```

---

## Traitement par lots

L’expiration peut concerner un grand nombre d’`Invitation`.

Le scheduler doit traiter des lots bornés.

Exemple conceptuel :

```text
Find expired pending Invitations
↓
Select bounded batch
↓
Dispatch one ExpireInvitation per aggregate
↓
Commit independently
↓
Continue from cursor
```

Chaque `Invitation` doit :

- appliquer sa propre transition ;
- conserver son propre audit ;
- produire son propre événement ;
- rester indépendante des échecs des autres éléments du lot.

---

## Pourquoi éviter une mise à jour de masse directe

Une commande SQL telle que :

```text
UPDATE invitations
SET status = 'Expired'
WHERE status = 'Pending'
AND expiration_date <= now()
```

peut contourner :

- les invariants de l’agrégat ;
- l’invalidation du token ;
- la production de `InvitationExpired` ;
- l’audit ;
- l’idempotence ;
- le contrôle de concurrence.

Une optimisation de masse ne peut être retenue que si elle préserve explicitement tous ces effets et reste équivalente au comportement de l’agrégat.

---

## Retard du scheduler

Un retard d’exécution ne prolonge pas l’Invitation.

Exemple :

```text
ExpirationDate: 10:00
Scheduler execution: 10:15
```

L’`Invitation` est inutilisable depuis :

```text
10:00
```

Son `ExpiredAt` peut être enregistré à :

```text
10:15
```

Le système ne doit pas conclure qu’elle est restée valide pendant ces quinze minutes.

---

## Indisponibilité du scheduler

Une indisponibilité prolongée du scheduler ne doit pas permettre l’acceptation d’une invitation échue.

Les commandes métier doivent continuer d’évaluer :

```text
CurrentTime >= ExpirationDate
```

Le scheduler peut ensuite réconcilier les états persistés.

---

## Fuseaux horaires

`ExpirationDate` et `ExpiredAt` doivent être représentés sous la forme d’instants non ambigus.

La représentation recommandée est :

```text
UTC instant
```

Les fuseaux horaires locaux peuvent être utilisés pour :

- l’affichage ;
- la saisie d’une politique d’échéance ;
- les communications.

Ils ne doivent pas introduire d’ambiguïté dans la comparaison métier.

---

## Précision temporelle

La politique doit définir la précision utilisée :

- seconde ;
- milliseconde ;
- microseconde.

La règle de frontière recommandée est :

```text
CurrentTime < ExpirationDate
    -> valid

CurrentTime >= ExpirationDate
    -> expired
```

À l’instant exact de l’échéance, l’Invitation est expirée.

---

## Horloge métier

Le domaine ne doit pas dépendre directement de l’horloge système globale.

L’instant doit être fourni par une abstraction telle que :

```text
Clock
```

Cela facilite :

- les tests ;
- la reproductibilité ;
- la simulation des frontières temporelles ;
- la cohérence entre les commandes.

---

## Nettoyage des données

L’expiration ne supprime pas immédiatement l’`Invitation`.

L’entité est conservée pour :

- l’audit ;
- l’idempotence ;
- la prévention des rejeux ;
- l’analyse des parcours ;
- le support ;
- les obligations de sécurité.

Une politique de rétention séparée peut ensuite :

- anonymiser certaines données ;
- supprimer les secrets ;
- archiver l’entité ;
- supprimer définitivement les données après le délai requis.

Cette politique ne fait pas partie de `ExpireInvitation`.

---

## Token

L’expiration doit rendre le token inutilisable dans la même unité atomique que la transition.

Selon l’implémentation, cela peut consister à :

- supprimer le hash actif ;
- marquer le token comme expiré ;
- augmenter une version de secret ;
- enregistrer un instant d’invalidation.

L’horodatage contenu dans le token ne remplace pas la vérification de l’état de l’agrégat.

---

## Notifications

Après `InvitationExpired`, un handler peut :

- arrêter les rappels ;
- mettre à jour les projections ;
- informer l’invitant ;
- informer le destinataire lorsque cela apporte une valeur ;
- proposer la création d’une nouvelle invitation ;
- enregistrer une métrique.

L’échec d’une notification ne doit pas annuler l’expiration.

---

## Intégrations

Les systèmes externes intéressés peuvent recevoir `InvitationExpired`.

Ils doivent traiter l’événement de manière idempotente.

Exemples d’usages :

- annulation d’un rappel programmé ;
- fermeture d’un parcours d’onboarding ;
- retrait d’un lien d’une interface ;
- mise à jour d’une projection analytique ;
- nettoyage d’un cache.

L’événement ne doit pas exposer le token.

---

## Sécurité

La commande doit garantir que :

- aucune invitation échue ne peut être utilisée ;
- le token devient inutilisable ;
- l’état persistant ne constitue pas l’unique contrôle ;
- les traitements différés réévaluent l’échéance ;
- le scheduler ne peut pas anticiper arbitrairement l’expiration ;
- les retries sont dédupliqués ;
- aucune information sensible n’est journalisée ;
- les comparaisons temporelles utilisent une horloge cohérente.

---

## Audit

Une expiration réussie doit enregistrer :

- `InvitationId`
- `WorkspaceId`
- `RoleId`
- `ExpirationDate`
- `ExpiredAt`
- `TriggeredBy`
- `ExecutionSource`
- `ExpirationRequestId`
- `CorrelationId`
- résultat

L’audit permet notamment de distinguer :

```text
expiration effective time
```

et :

```text
persistence or detection time
```

Il ne doit contenir aucun token brut.

---

## Décisions de conception

### L’expiration est déterminée par le temps

`ExpireInvitation` ne décide pas arbitrairement de fermer l’Invitation.

Elle constate que la condition temporelle définie lors de la création est devenue vraie.

---

### Le scheduler n’est pas la source de vérité

Une invitation est expirée à l’échéance, même si aucun worker ne l’a encore mise à jour.

Chaque commande doit vérifier l’échéance elle-même.

---

### Expired est un état terminal

Une invitation expirée ne peut pas être prolongée ou réactivée.

Pour inviter à nouveau le destinataire, une nouvelle `Invitation` doit être créée.

---

### L’échéance reste immuable

`ExpireInvitation` ne modifie pas `ExpirationDate`.

Elle enregistre seulement que cette date est atteinte.

---

### Aucun Membership n’est affecté

L’expiration concerne une autorisation temporaire non consommée.

Elle ne modifie aucune appartenance existante.

---

### Le token est invalidé atomiquement

L’état `Expired` et l’inutilisabilité du token doivent toujours être cohérents.

---

### L’entité est conservée

L’`Invitation` expirée reste disponible pour l’audit et l’idempotence.

La suppression physique relève d’une politique de rétention distincte.

---

## Synthèse

`ExpireInvitation` matérialise la fin temporelle d’une `Invitation` encore active.

Elle garantit que :

- l’échéance est réellement atteinte ;
- une seule transition terminale est appliquée ;
- l’état devient `Expired` ;
- le token devient inutilisable ;
- aucune acceptation ou relance future n’est possible ;
- aucun `Membership` n’est créé ou modifié ;
- le retard ou l’indisponibilité du scheduler ne prolonge jamais la validité ;
- les retries et traitements concurrents ne produisent aucun doublon.

Le résultat final est :

```text
Pending Invitation
    ↓
Expired Invitation
```

dès que :

```text
CurrentTime >= ExpirationDate
```