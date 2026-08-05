---
id: NTF-WORKFLOWS
title: Notifications Workflows
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - notification-policy.md
  - lifecycle.md
  - invariants.md
  - commands/README.md
  - processors/README.md
  - events.md
  - integrations.md
---

# Workflows

## Planification nominale

1. Notifications reçoit `RecommendationEvaluationCompleted`.
2. `ProcessAdvisorNotificationSignal` authentifie l'enveloppe et déduplique
   EventId avec NotificationPolicyVersion.
3. Le processeur crée ou reprend NotificationPlan.
4. Il relit l'AdvisorOverview exact par RecommendationEvaluationId.
5. Il relit la Recommendation courante par PrimaryRecommendationId.
6. Il vérifie SourceEligibility, identité, status Generated et validité.
7. Il vérifie que le Workspace est Active et fige sa locale.
8. Identity résout l'AudienceSnapshot autorisée.
9. Il acquiert TopicProcessingLease et compare AdvisorOverviewVersion au cursor.
10. Pour chaque destinataire, il lit les préférences effectives et compare le
   thread actif au ContentFingerprint.
11. Il résout les threads actifs dont le destinataire n'appartient plus à
   AudienceSnapshot et annule leurs NotificationDelivery Pending.
12. Il sélectionne ou supprime InApp et Email avec une raison explicite.
13. Il crée la Notification et l'éventuelle NotificationDelivery Email
    atomiquement.
14. Lorsque toutes les décisions sont durables, le plan devient Completed,
    avance NotificationTopicCursor avec le même fencing token et publie
    `NotificationPlanCompleted`.

## Priorité inchangée

Si RecommendationId et ContentFingerprint sont identiques, RecipientDecision
vaut Unchanged. La Notification existante n'est ni réécrite, ni remise en
Unread, et aucun nouvel e-mail n'est demandé.

## Source historique ou inéligible

Si SourceEligibility n'est pas Eligible ou si AdvisorOverviewVersion est
inférieure au cursor, le plan conserve SourceIgnored et se termine sans modifier
les threads courants. Une évaluation historique ou un événement livré hors ordre
ne peut donc ni masquer ni remplacer une priorité plus récente.

## Nouvelle priorité

Une nouvelle PrimaryRecommendation pour le même destinataire :

1. rend la Notification Active du thread Superseded ;
2. annule ses NotificationDelivery Pending ; une livraison Dispatching converge
   séparément vers son outcome fournisseur ;
3. crée une nouvelle NotificationId avec son propre état Unread ;
4. applique à nouveau consentement, importance et fréquence ;
5. autorise une unique escalade Email High vers Critical dans la fenêtre.

La nouvelle création ne modifie jamais l'historique de lecture de l'ancienne.

## Source vide ou terminale

Un overview Eligible devenu vide résout tous les threads actifs du topic.
RecommendationCompleted ou RecommendationDismissed résout uniquement les
messages liés à la Recommendation exacte. RecommendationExpired ou DisplayUntil
atteint les expire. Ces événements terminaux ne résolvent aucune nouvelle
audience et restent applicables dans un Workspace restreint. Aucune transition
ne marque automatiquement le message Read.

## Préférences

L'absence de préférence persistée retourne InApp Enabled et Email Disabled.
L'utilisateur peut activer ImportantOnly avec consentement explicite, même si
aucun endpoint vérifié n'est actuellement disponible. Le choix ne rejoue jamais
l'historique. Une désactivation rend les NotificationDelivery Pending
inéligibles ; leur prochain dispatch les fait passer à Cancelled avant tout
effet externe.

## Livraison Email

1. `NotificationDeliveryRequested` rend une NotificationDelivery Pending
   disponible.
2. Le worker acquiert DispatchLease par ExpectedRevision.
3. Il revalide Workspace, destinataire, permissions, préférence, endpoint,
   validité et fréquence.
4. Il acquiert le FrequencyLease exclusif de l'EmailFrequencyKey puis passe
   atomiquement la livraison à Dispatching.
5. Il rend le template allowlisté et soumet avec ProviderIdempotencyKey.
6. Une acceptation crée `NotificationDeliveryAccepted` et consomme la fenêtre.
7. Un callback authentique ultérieur crée
   `NotificationDeliveryConfirmed` ou `NotificationDeliveryFailed`.

Le produit peut afficher Accepted comme « pris en charge », jamais comme
« livré ».

## Échec et retry

Un échec transitoire certain replace NotificationDelivery en Pending avec
NextAttemptAt et backoff borné. Un timeout ambigu réutilise strictement la même
ProviderIdempotencyKey et conserve le FrequencyLease. Un échec permanent ou les
tentatives épuisées rend NotificationDelivery Failed sans retirer la
Notification InApp.

## Lecture utilisateur

Le destinataire liste uniquement ses messages, puis marque une Notification
Unread comme Read avec ExpectedRevision. Il peut ensuite la remettre Unread.
Ces commandes n'envoient rien, ne changent pas NotificationStatus et n'exécutent
pas RecommendationAction.

## Ouverture de l'action

Le client ouvre l'ActionDescriptor allowlisté après authentification. Le domaine
cible vérifie ses propres capacités et traite toute commande directement. Un
refus ou un succès externe ne complète, ne lit et ne résout aucune Notification
automatiquement.
