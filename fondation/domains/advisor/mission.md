---
id: ADV-MISSION
title: Advisor Mission
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - scope.md
  - philosophy.md
  - recommendation-policy.md
  - ../../vision/vision.md
  - ../../vision/principles.md
  - ../../../evolution/roadmap/mvp-scope.md
---

# Mission

## Raison d'être

Comprendre une fragilité ne suffit pas toujours à agir. Un indépendant doit
encore choisir une action, évaluer son urgence et éviter de se disperser entre
plusieurs alertes concurrentes.

> Advisor réduit cette charge de décision en proposant la prochaine action la
> plus pertinente, avec ses preuves, ses limites et un contrôle humain total.

## Promesse

Advisor permet :

- d'identifier une priorité principale et au plus deux alternatives ;
- de comprendre pourquoi chaque Recommendation existe maintenant ;
- de connaître son impact qualitatif, son urgence, sa confiance et son effort ;
- d'accéder directement au module Atlas où l'action peut être réalisée ;
- de confirmer l'accomplissement ou de rejeter la proposition ;
- de conserver un historique sans réactiver les décisions passées.

## Contribution à Atlas

Advisor clôt la chaîne de décision sans franchir la frontière d'exécution :

```text
faits observés       -> CRM et Billing
mesures              -> Analytics
interprétation       -> Business Health
action proposée      -> Advisor
action accomplie     -> utilisateur dans le domaine propriétaire
diffusion            -> Notifications
```

## Critère de réussite

Pour toute Recommendation, Atlas peut répondre :

- quelle BusinessHealthAssessment et quelle politique ont été appliquées ;
- quelle règle a produit l'action ;
- pourquoi elle est classée avant les autres ;
- quelle incertitude et quelle limite subsistent ;
- quelle capacité sera exigée dans le domaine cible ;
- si l'utilisateur l'a accomplie, rejetée ou laissée expirer.
