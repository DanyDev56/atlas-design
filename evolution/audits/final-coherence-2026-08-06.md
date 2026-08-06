---
id: AUDIT-001
title: Audit final de cohérence Atlas
status: Complete
owner: Product and Architecture
date: 2026-08-06
baseline: 50401bb
reviewed_branch: codex/finalisation-atlas
---

# Audit final de cohérence Atlas — 6 août 2026

## Périmètre et méthode

L'audit couvre les 361 documents Markdown, les huit bounded contexts consolidés,
la Constitution, le Product Language, les cartes globales, le MVP, le Blueprint,
la gouvernance et les contrôles CI. Il combine revue des responsabilités,
traçage des parcours de bout en bout, recherche de liens/IDs/RouteKeys invalides
et exécution de tous les contrôles spécialisés.

## Verdict

**Cohérent pour entrer en implémentation MVP, sous réserve des arbitrages non
bloquants ci-dessous.** Aucun changement de Constitution, aucune fusion de
bounded contexts et aucun nouveau propriétaire de données n'ont été introduits.

## Matrice de clôture

| Sujet audité | Preuve | Résultat |
|---|---|---|
| démarrage à froid | chemin manuel, import confirmé, première valeur mesurée | conforme |
| boucle décisionnelle | responsabilités Health/Advisor/Notifications et dégradation | conforme |
| politiques | cadence, pré-calcul, activation, rollback et versions persistées | conforme |
| stratégie et MVP | wedge, exclusions, définition de terminé et mesures | conforme |
| mobile-first | cinq destinations, états, accessibilité et deep links | conforme |
| slices MVP | une slice démontrable et des gates pour chaque domaine | conforme |
| architecture globale | trois ADR acceptés, événements et stockage privé | conforme |
| navigation contractuelle | registre exhaustif des RouteKeys MVP | conforme |
| assurance documentaire | CI sans dépendance, checks globaux et spécialisés | conforme |

## Vérification des frontières

- Identity possède authentification, Membership, rôles et sessions ; Workspace
  possède le contexte d'activité et son cycle de vie.
- CRM possède la relation commerciale ; Billing possède documents, montants et
  paiements. L'import ne possède aucun fait : il traduit une ligne en commande.
- Analytics projette les faits sans les réinterpréter comme décision.
- Business Health explique l'état sans choisir d'action ; Advisor choisit la
  priorité sans recalculer la santé ; Notifications choisit la remise sans
  modifier la recommandation.
- Dashboard et shell composent les lectures et routes ; ils ne deviennent pas des
  bounded contexts cachés.

## Cohérence terminologique

Les concepts canoniques (`Workspace`, `Client`, `Quote`, `Invoice`,
`AnalyticsSnapshot`, `BusinessHealthAssessment`, `Recommendation`,
`Notification`, versions de politique et `RouteKey`) gardent leur propriétaire.
Les libellés français de navigation ne remplacent pas les noms de contrat. Les
termes Projects, Automations, Marketplace, banque et multi-workspaces restent
explicitement hors MVP.

## Risques résiduels maîtrisés

| Risque | Traitement actuel | Gate avant production |
|---|---|---|
| import de qualité variable | preview, erreurs par ligne, aucune fusion silencieuse | corpus de fichiers anonymisés |
| cohérence éventuelle visible | états de calcul et dernier état fiable | test de panne à chaque frontière |
| évolution coordonnée des politiques | matrice de compatibilité et activation en deux phases | exercice de rollback |
| deep link périmé/interdit | fallback autorisé du registre | tests positifs et négatifs par clé |
| dérive documentaire | workflow bloquant et checks sans réseau | protection de branche GitHub |

## Arbitrages restant à prendre

Ces points ne remettent pas en cause le modèle et ne doivent pas être décidés
implicitement pendant l'implémentation :

1. durée exacte de conservation du fichier CSV brut, dans la limite courte déjà
   imposée, avec validation Security/Legal ;
2. fournisseur d'e-mail MVP et objectifs contractuels associés ;
3. validation par données de production anonymisées des SLO proposés pour la
   boucle de décision ;
4. ordre de déploiement précis et capacité d'équipe par slice ;
5. seuils de succès chiffrés des métriques d'activation après instrumentation.

Chaque arbitrage modifiant durablement une frontière, une sémantique de politique
ou une promesse utilisateur doit créer ou amender un ADR.

## Contrôles exécutés

- `python3 scripts/check-repository-docs.py` : liens, IDs et RouteKeys valides ;
- huit scripts `scripts/check-*-docs.sh` : tous les groupes passent ;
- `git diff --check` : aucune erreur de whitespace.

## Recommandation

Protéger `main` avec le workflow documentaire, puis implémenter les slices dans
l'ordre publié. Une release candidate n'est acceptable qu'après la démonstration
à froid et le test de reprise de la chaîne complète sans doublon visible.
