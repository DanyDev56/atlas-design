---
id: NTF-NOTIFICATION-POLICY
title: Notification Policy 1.0
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - scope.md
  - lifecycle.md
  - model.md
  - value-objects.md
  - invariants.md
  - ../advisor/recommendation-policy.md
---

# Notification Policy 1.0

`NotificationPolicyVersion = 1.1.0` désigne les règles ci-dessous. La politique
est globale, immuable et non configurable par Workspace.

## Sources supportées

| SourceEvent | Effet autorisé |
|---|---|
| `AdvisorOverviewChanged` | créer, conserver, remplacer, résoudre ou expirer la notification de priorité |

Les événements Recommendation et `RecommendationEvaluationCompleted` ne sont
pas des déclencheurs : toute cause passe d'abord par la convergence complète de
l'AdvisorOverview.

## Éligibilité de l'évaluation

Une nouvelle notification exige :

- `SourceEligibility = Eligible` ;
- une `PrimaryRecommendationId` présente ;
- une NotificationAdvisorOverview relue par AdvisorOverviewVersion ;
- une NotificationRecommendationView courante confirmant la PrimaryRecommendation
  encore Generated et non expirée ;
- une cohérence exacte entre événement et contrat relu ;
- un Workspace Active.

Une source historique ou incompatible ne publie aucun AdvisorOverviewChanged.
Un overview courant devenu vide après `SourceInvalidated` ou une mutation
terminale résout l'ancien thread sans créer de contenu de remplacement.

## Ordre Advisor

Chaque AdvisorOverviewChanged expose AdvisorOverviewVersion, ConvergenceKind et
SourceOrder. NotificationTopicCursor sérialise les plans pour `(WorkspaceId,
NotificationTopic)` et compare la version au dernier overview appliqué :

- version inférieure : SourceIgnored sans mutation ;
- version égale et même contrat : rejeu ou Unchanged ;
- version égale mais contenu incompatible : SourceContractMismatch ;
- version supérieure : évaluation normale puis progression du cursor après
  completion durable du plan.

L'ordre d'arrivée du bus ne peut donc jamais rétablir une ancienne priorité.

## Audience

Pour une évaluation susceptible de créer un message, Identity résout les Users
qui possèdent simultanément, dans le même Workspace :

```text
advisor.recommendations.read
notifications.inbox.read
```

Chaque destinataire doit avoir User, Membership et Role actifs. La réponse
contient UserId, MembershipId, AudienceVersion et une
DeliveryEndpointReference optionnelle, jamais l'adresse brute.

Un destinataire retiré entre planification et dispatch n'est pas livré : le
processeur revalide son endpoint et sa capacité avant l'effet externe. Une
Notification Active d'un ancien destinataire absent du nouvel AudienceSnapshot
est Resolved et sa NotificationDelivery Pending est Cancelled.

Une version vide ou remplaçant la priorité ne recalcule pas une audience pour
les threads à clôturer : elle s'applique aux Notification existantes, y compris
pour un ancien destinataire ou un Workspace restreint. Elle ne crée aucun
message sans nouvelle PrimaryRecommendation éligible.

## Préférences par défaut

```text
InAppMode = Enabled
EmailMode = Disabled
```

`EmailMode = ImportantOnly` exige un opt-in explicite et daté. Une modification
ne renvoie jamais les notifications historiques.

## Décision de canal

| Canal | Conditions |
|---|---|
| `InApp` | InAppMode Enabled et audience toujours autorisée |
| `Email` | EmailMode ImportantOnly, endpoint vérifié, Priority High ou Critical, limite de fréquence satisfaite |

Si aucun canal n'est éligible, NotificationPlan conserve `AllChannelsSuppressed`
et aucune Notification n'est créée.

## Contenu

### InApp

Le contenu copie uniquement :

```text
NotificationTemplateKey
NotificationTemplateVersion
Locale
RecommendationId
RecommendationPriority
SummaryTemplateData
RecommendationAction
ValidUntil
```

### Email

L'e-mail indique seulement qu'une nouvelle priorité High ou Critical est
disponible dans Atlas. Il ne contient ni montant, Client, document, preuve ou
action directe. Un lien ouvre la Notification après authentification et un
second ouvre la gestion des préférences. Aucun lien ne contient de secret ou
n'exécute une RecommendationAction.

Tous les champs et templates sont allowlistés et versionnés.

## Déduplication et thread

```text
NotificationPlanKey = SourceEventId + NotificationPolicyVersion

NotificationThreadKey = WorkspaceId + RecipientUserId
                        + AdvisorPrimaryRecommendation

ContentFingerprint = PrimaryRecommendationId + NotificationTemplateKey
                     + NotificationTemplateVersion
                     + RecommendationAction identity
```

- même PrimaryRecommendation et même fingerprint : `Unchanged`, aucun nouvel
  item ni e-mail ;
- nouvelle PrimaryRecommendation : ancienne Notification Active devient
  Superseded, puis une nouvelle est créée ;
- événement terminal : Notification liée devient Resolved ou Expired ;
- un rejeu du SourceEventId retourne le NotificationPlan initial.

## Limite de fréquence Email

- au plus une NotificationDelivery Email Accepted par recipient, Workspace et
  topic sur une fenêtre glissante de 24 heures ;
- une nouvelle Priority Critical peut contourner une fois une livraison High
  précédente dans la fenêtre ;
- une autre Critical ne contourne pas une Critical déjà acceptée ;
- une suppression Email n'empêche jamais le canal InApp ;
- la fenêtre utilise AcceptedAt, pas RequestedAt.

Le dispatch est sérialisé par `EmailFrequencyKey = WorkspaceId +
RecipientUserId + NotificationTopic`. Un FrequencyLease exclusif est acquis
avant l'appel fournisseur. Une acceptation écrit le slot AcceptedAt ; un échec
certain libère le lease ; un timeout ambigu le conserve jusqu'à résolution avec
la même ProviderIdempotencyKey.

## Livraison

- ValidUntil doit être futur lors du dispatch ;
- un endpoint opaque est revalidé juste avant l'envoi ;
- le fournisseur reçoit une ProviderIdempotencyKey stable ;
- un timeout ambigu est relu ou rejoué avec cette même clé ;
- Accepted signifie prise en charge, jamais Delivered ;
- Delivered exige une preuve fournisseur authentique ;
- un échec transient est retenté avec backoff borné ;
- un échec permanent ou le maximum de tentatives rend NotificationDelivery
  Failed.
