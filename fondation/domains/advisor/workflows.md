---
id: ADV-WORKFLOWS
title: Advisor Workflows
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-06

references:
  - model.md
  - recommendation-policy.md
  - recommendation-lifecycle.md
  - invariants.md
  - commands/README.md
  - processors/README.md
  - events.md
  - integrations.md
---

# Workflows

## Évaluation nominale

1. Advisor reçoit `BusinessHealthAssessed`.
2. `EvaluateRecommendations` authentifie l'enveloppe et déduplique EventId.
3. Il dérive EvaluateRecommendationsRequestId de la source et de la politique.
4. Il relit la BusinessHealthAssessment exacte puis la vue courante.
5. Il vérifie status, fiabilité, politique, âge et SourceOrder.
6. Il crée ou reprend RecommendationEvaluation.
7. Il évalue les cinq règles et calcule tous les candidats.
8. Il ordonne les candidats et conserve au plus les trois premiers.
9. Pour chaque règle, il génère, réaffirme, expire ou supprime le candidat.
10. Il appelle `RebuildAdvisorOverview` avec la CauseKey de l'évaluation.
11. Le processeur reconstruit l'overview, avance sa version et publie
    `AdvisorOverviewChanged`.
12. Il marque l'évaluation Completed et publie
    `RecommendationEvaluationCompleted`.

## Zéro recommandation

Une source Strong sans risque ou déjà couverte par des fingerprints terminaux
peut produire zéro Recommendation. L'évaluation conserve la décision de chaque
règle, expire les anciens prédicats devenus invalides et publie un overview vide.

Une source courante insuffisante ou obsolète suit une convergence invalidante :
les Recommendation Generated expirent avec `SourceBecameIneligible`, puis
l'overview vide est publié. Une source historique ou contractuellement
incompatible ne modifie ni Recommendations, ni overview, ni version.

## Réaffirmation

Si une nouvelle source confirme le même fingerprint :

1. le processeur verrouille la Recommendation par ExpectedRevision ;
2. il ajoute une EvidenceRevision immuable ;
3. il avance CurrentEvidenceRevision ;
4. il recalcule ValidUntil depuis le nouveau SourceAsOf ;
5. il publie `RecommendationReaffirmed` sans notifier une nouvelle génération.

## Changement de contexte

Si un fingerprint matériel change, l'ancienne Recommendation expire avec
`ContextChanged`. Une nouvelle identité est ensuite générée si le candidat reste
dans le top trois. Si le prédicat disparaît ou sort du top trois, seule
l'expiration correspondante est produite.

## Événement tardif

Une BusinessHealthAssessment plus ancienne termine sa RecommendationEvaluation
avec `SourceEligibility = HistoricalSource`. Elle ne réaffirme, ne génère et
n'expire rien. Les autres Workspaces continuent indépendamment.

## Completion

1. l'utilisateur suit éventuellement RecommendationAction ;
2. le domaine cible autorise et traite sa propre intention ;
3. l'utilisateur confirme séparément l'accomplissement dans Advisor ;
4. `CompleteRecommendation` vérifie permission, révision, statut et validité ;
5. Recommendation devient Completed et publie RecommendationCompleted.
6. `RebuildAdvisorOverview` retire l'entrée et promeut l'alternative suivante.

Cette séquence ne prétend ni observer automatiquement la mutation source ni
mesurer son impact.

## Dismissal

L'utilisateur choisit un DismissalReason structuré. La transition terminale est
atomique. Un même fingerprint ne revient pas à l'évaluation suivante tant que
son prédicat n'a pas disparu ou changé matériellement.

`RecommendationDismissed` déclenche la même reconstruction et promotion que la
completion.

## Expiration temporelle

Le scheduler sélectionne les Recommendation Generated dont ValidUntil est
atteint. `ExpireRecommendation` vérifie ClockProof et ExpectedRevision, puis
publie RecommendationExpired. Un traitement concurrent déjà terminal converge
sans seconde transition.

Chaque expiration déclenche `RebuildAdvisorOverview` ; Notifications attend le
nouvel `AdvisorOverviewChanged` au lieu de réagir à l'événement terminal isolé.

## Lecture utilisateur

L'overview retourne la priorité principale puis deux alternatives au maximum.
Avant d'ouvrir l'action, le client vérifie RequiredCapabilities. Un refus du
domaine cible n'altère pas la Recommendation et reste expliqué sans contournement.
