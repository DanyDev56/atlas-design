---
id: NTF-DECISIONS
title: Notifications Decision Record
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - mission.md
  - scope.md
  - notification-policy.md
  - model.md
  - invariants.md
---

# Registre des décisions

| ID | Décision | Conséquence 1.0 |
|---|---|---|
| `NTF-ADR-001` | Advisor est l'unique source métier. | Le MVP reste borné et n'est pas un bus générique. |
| `NTF-ADR-002` | AdvisorOverviewChanged déclenche seul la diffusion. | Évaluations et mutations terminales arrivent après la même convergence stable. |
| `NTF-ADR-003` | Une NotificationPolicy est globale et versionnée. | Déduplication, canaux et fréquence restent reproductibles. |
| `NTF-ADR-004` | L'audience vient d'Identity avec permissions effectives. | Notifications ne devient pas propriétaire des destinataires. |
| `NTF-ADR-005` | Les préférences appartiennent à Notifications. | Le consentement de canal reste séparé de l'identité. |
| `NTF-ADR-006` | InApp est enabled et Email disabled par défaut. | L'inbox fonctionne sans e-mail non consenti. |
| `NTF-ADR-007` | Email est limité aux priorités High et Critical. | « E-mails importants » a une définition contractuelle. |
| `NTF-ADR-008` | L'e-mail ne contient aucune donnée métier détaillée. | Une boîte externe ne révèle pas le contexte financier. |
| `NTF-ADR-009` | Les endpoints sont des références opaques. | Aucune adresse brute n'est stockée dans le domaine. |
| `NTF-ADR-010` | Une seule Notification active existe par thread. | Une nouvelle priorité remplace l'ancienne sans bruit cumulatif. |
| `NTF-ADR-011` | La même PrimaryRecommendation est Unchanged. | Une réaffirmation quotidienne ne renvoie pas le même message. |
| `NTF-ADR-012` | La limite Email est une Accepted par 24 heures. | Les retries et échecs avant acceptation ne consomment pas faussement la fenêtre. |
| `NTF-ADR-013` | Une unique escalade High vers Critical peut contourner la fenêtre. | Une aggravation importante reste visible sans spam Critical répété. |
| `NTF-ADR-014` | ReadState est orthogonal à NotificationStatus. | Résolution et lecture ne sont jamais confondues. |
| `NTF-ADR-015` | Accepted et Delivered sont distincts. | Atlas ne promet jamais une remise non prouvée. |
| `NTF-ADR-016` | ProviderIdempotencyKey survit aux retries. | Un timeout ambigu ne produit pas de doublon externe. |
| `NTF-ADR-017` | Identity conserve ses communications sensibles. | Tokens et e-mails de sécurité ne traversent pas Notifications 1.0. |
| `NTF-ADR-018` | Billing conserve ses envois documentaires. | Une notification produit ne devient pas une preuve de livraison financière. |
| `NTF-ADR-019` | Clics et conversions restent hors domaine. | Product Analytics mesure l'interface sans polluer le cycle métier. |
| `NTF-ADR-020` | Aucune RecommendationAction n'est exécutée par Notifications. | Lire ou cliquer conserve le contrôle utilisateur et les frontières source. |
| `NTF-ADR-021` | AdvisorOverviewVersion est sérialisée par Workspace et topic. | Un événement Advisor livré tardivement ne rétablit jamais une ancienne priorité. |
