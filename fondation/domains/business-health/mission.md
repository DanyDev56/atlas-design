---
id: BHL-MISSION
title: Business Health Mission
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - README.md
  - scope.md
  - health-policy.md
  - ../../vision/principles.md
  - ../../vision/anti-goals.md
  - ../../../evolution/roadmap/mvp-scope.md
---

# Mission

## Raison d'être

Un indépendant dispose rarement du temps ou du recul nécessaires pour relier
pipeline, facturation, encaissements et concentration Client. Une collection de
chiffres ne suffit pas : Atlas doit rendre la situation compréhensible sans
inventer une certitude.

> Business Health transforme des mesures Analytics fiables en une lecture
> synthétique, datée, explicable et orientée vers le jugement humain.

## Promesse

Business Health permet :

- de connaître un `OverallScore` et son `HealthBand` ;
- d'identifier les facteurs qui soutiennent ou fragilisent l'activité ;
- de suivre l'évolution par rapport à une évaluation comparable ;
- de voir les risques directement étayés par les données disponibles ;
- de distinguer résultat fiable, résultat limité et données insuffisantes ;
- de comprendre pourquoi une zone constitue la `PrimaryAttention`.

## Contribution à Atlas

Business Health maintient cette séparation :

```text
faits métier          -> CRM et Billing
mesures               -> Analytics
interprétation        -> Business Health
action proposée       -> Advisor
diffusion             -> Notifications
```

Le score réduit la charge d'interprétation. Il ne remplace ni le détail des
mesures, ni le contexte de l'utilisateur, ni sa décision.

## Critère de réussite

Pour toute évaluation, Atlas peut répondre :

- quel snapshot et quelle politique ont été appliqués ;
- quelles preuves expliquent chaque facteur et chaque risque ;
- comment la couverture et la fraîcheur limitent la conclusion ;
- pourquoi la tendance et la PrimaryAttention ont été choisies ;
- ce qui est observé, ce qui est interprété et ce qui reste inconnu.
