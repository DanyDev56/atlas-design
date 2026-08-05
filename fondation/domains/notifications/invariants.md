---
id: NTF-INVARIANTS
title: Notifications Invariants
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - notification-policy.md
  - lifecycle.md
  - aggregates.md
  - value-objects.md
  - commands/README.md
  - processors/README.md
---

# Invariants

## Isolation, source et politique

| ID | Règle |
|---|---|
| `NTF-INV-001` | Tout plan, Notification, préférence, NotificationDelivery, permission et référence appartient à un seul WorkspaceId. |
| `NTF-INV-002` | Les identifiants Notifications sont stables, uniques et jamais réattribués. |
| `NTF-INV-003` | Une NotificationPolicy et un template publiés sont globaux, immuables et versionnés. |
| `NTF-INV-004` | Toute Notification référence un événement Advisor authentique, supporté et du même Workspace. |
| `NTF-INV-005` | RecommendationGenerated ne déclenche jamais une planification 1.0. |
| `NTF-INV-006` | Une création exige un AdvisorOverview exact, stabilisé, Eligible, de version supérieure au cursor ou identique compatible, et contenant une PrimaryRecommendation Generated non expirée. |
| `NTF-INV-007` | Notifications ne recalcule ni Priority, ni Action, ni validité Advisor. |
| `NTF-INV-008` | Il existe au plus un NotificationPlan par `(SourceEventId, NotificationPolicyVersion)` et un seul TopicProcessingLease valide par NotificationTopicCursor. |
| `NTF-INV-009` | Une source inéligible ou de version Advisor inférieure termine un plan SourceIgnored sans mutation courante ; seul un overview Eligible et monotone vide peut résoudre un thread sans contenu inventé. |
| `NTF-INV-010` | Notifications 1.0 ne consomme aucun événement Identity sensible, Billing document delivery, CRM ou Analytics. |

## Audience, confidentialité et préférences

| ID | Règle |
|---|---|
| `NTF-INV-011` | Toute création exige User, Membership et Role actifs avec toutes les permissions requises au moment de la résolution d'audience. |
| `NTF-INV-012` | RecipientUserId de la Notification correspond exactement au UserId de l'AudienceSnapshot. |
| `NTF-INV-013` | Une adresse e-mail brute n'est jamais persistée dans un agrégat, événement, outbox, log ou audit Notifications. |
| `NTF-INV-014` | DeliveryEndpointReference est opaque, bornée au destinataire et revalidée immédiatement avant dispatch. |
| `NTF-INV-015` | Les defaults sont InApp Enabled et Email Disabled. |
| `NTF-INV-016` | Email ImportantOnly exige un opt-in explicite, daté et non révoqué. |
| `NTF-INV-017` | Une préférence s'applique uniquement aux décisions futures ; elle ne crée aucun envoi rétroactif. |
| `NTF-INV-018` | Une désactivation peut annuler une NotificationDelivery Pending mais ne retire jamais un message déjà Accepted. |
| `NTF-INV-019` | Une restriction ou fermeture Workspace empêche toute nouvelle création et tout dispatch ordinaire, jamais la clôture d'une Notification existante. |
| `NTF-INV-020` | Tout contenu externe est minimisé et ne contient ni montant, Client, document, preuve ou secret. |

## Planification, déduplication et contenu

| ID | Règle |
|---|---|
| `NTF-INV-021` | Il existe au plus une Notification Active par NotificationThreadKey. |
| `NTF-INV-022` | Même PrimaryRecommendationId et même ContentFingerprint produisent Unchanged, jamais un nouvel item ou e-mail. |
| `NTF-INV-023` | Une nouvelle PrimaryRecommendation rend l'ancienne Notification Active Superseded avant de créer la nouvelle. |
| `NTF-INV-024` | Un événement terminal Advisor ne résout ou expire que les Notification liées à la Recommendation exacte. |
| `NTF-INV-025` | NotificationContent utilise uniquement templates, locales, données et ActionDescriptor allowlistés. |
| `NTF-INV-026` | DisplayUntil ne dépasse jamais ValidUntil de la Recommendation source. |
| `NTF-INV-027` | Email n'est sélectionné que pour Priority High ou Critical avec endpoint et opt-in valides. |
| `NTF-INV-028` | Une fenêtre de 24 heures admet au plus une NotificationDelivery Email Accepted par destinataire, Workspace et topic, sauf unique escalade High vers Critical. |
| `NTF-INV-029` | Toute sélection ou suppression de canal conserve sa raison, préférence, politique et instant de décision. |
| `NTF-INV-030` | Tous canaux supprimés produit AllChannelsSuppressed et aucune Notification racine. |

## Pertinence et lecture

| ID | Règle |
|---|---|
| `NTF-INV-031` | NotificationStatus appartient à Active, Resolved, Superseded ou Expired et tout statut terminal possède une NotificationTerminalReason compatible. |
| `NTF-INV-032` | Resolved, Superseded et Expired ne redeviennent jamais Active. |
| `NTF-INV-033` | Une nouvelle pertinence crée une nouvelle NotificationId. |
| `NTF-INV-034` | NotificationReadState est indépendant de NotificationStatus et ne change jamais automatiquement avec lui. |
| `NTF-INV-035` | Un canal InApp sélectionné commence Unread ; sans canal InApp, ReadState vaut NotApplicable. |
| `NTF-INV-036` | Seul RecipientUserId peut marquer sa Notification read ou unread. |
| `NTF-INV-037` | Mark read/unread n'altère ni contenu, source, statut, NotificationDelivery ou préférence. |

## Livraison

| ID | Règle |
|---|---|
| `NTF-INV-038` | Il existe au plus une NotificationDelivery par `(NotificationId, NotificationChannel)`. |
| `NTF-INV-039` | Une NotificationDelivery possède une ProviderIdempotencyKey stable, jamais régénérée après timeout ou retry. |
| `NTF-INV-040` | Une seule tentative détient un DispatchLease non expiré et un seul dispatch Email détient le FrequencyLease de son EmailFrequencyKey. |
| `NTF-INV-041` | Chaque DeliveryAttempt est immuable, numérotée sans trou et conserve son résultat structuré. |
| `NTF-INV-042` | Accepted signifie uniquement prise en charge fournisseur et n'est jamais présenté comme Delivered. |
| `NTF-INV-043` | Delivered exige un ProviderDeliveryProof authentique correspondant à la ProviderMessageReference. |
| `NTF-INV-044` | ProviderOutcomeId est appliqué au plus une fois ; un contenu incompatible est refusé. |
| `NTF-INV-045` | Un échec transient retourne Pending avec NextAttemptAt ; un échec permanent ou des tentatives épuisées vaut Failed. |
| `NTF-INV-046` | Une NotificationDelivery Pending devenue non pertinente ou non consentie est Cancelled avant tout effet externe. |
| `NTF-INV-047` | Une NotificationDelivery Suppressed ou Cancelled n'appelle jamais le fournisseur. |
| `NTF-INV-048` | Delivered, Failed, Suppressed et Cancelled sont terminaux ; Accepted peut seulement devenir Delivered ou Failed. |

## Autorité, concurrence et audit

| ID | Règle |
|---|---|
| `NTF-INV-049` | Lecture et mutations humaines exigent un acteur actif, le même Workspace, la capacité exacte et RecipientUserId. |
| `NTF-INV-050` | Planification, dispatch, outcome et expiration exigent leur capacité SystemActorOnly bornée. |
| `NTF-INV-051` | Toute mutation d'une racine existante exige ExpectedRevision ou preuve de callback équivalente. |
| `NTF-INV-052` | Chaque commande et processeur possède un RequestId ; rejeu identique retourne le résultat initial et réutilisation incompatible échoue. |
| `NTF-INV-053` | Mutation d'agrégat, Domain Events et outbox sont atomiques. |
| `NTF-INV-054` | Les consommateurs dédupliquent EventId et les plans reprennent sans doubler une décision ou une NotificationDelivery. |
| `NTF-INV-055` | Notifications n'exécute aucune RecommendationAction et ne modifie aucun domaine source. |
| `NTF-INV-056` | Les affichages, clics et conversions sont de la télémétrie produit, pas des états ou événements Notifications. |
| `NTF-INV-057` | L'historique 1.0 conserve les références minimales de source, audience, préférence, contenu, canal, tentative et suppression ; aucun effacement implicite n'existe avant une politique de rétention versionnée. |
| `NTF-INV-058` | Notifications 1.0 ne présente ni acceptation fournisseur comme livraison, ni message comme décision métier. |
