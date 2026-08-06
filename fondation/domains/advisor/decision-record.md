---
id: ADV-DECISIONS
title: Advisor Decision Record
status: In Review
owner: Product
version: 1.2.0
last_updated: 2026-08-06

references:
  - mission.md
  - scope.md
  - recommendation-policy.md
  - model.md
  - invariants.md
---

# Registre des décisions

| ID | Décision | Conséquence 1.0 |
|---|---|---|
| `ADV-ADR-001` | Business Health est l'unique source de génération. | Advisor ne recalcule ni métrique, facteur ou risque. |
| `ADV-ADR-002` | Une RecommendationPolicy est globale, immuable et versionnée. | Toute proposition reste reproductible. |
| `ADV-ADR-003` | Cinq règles déterministes composent le MVP. | Aucun moteur opaque ou génératif n'est nécessaire. |
| `ADV-ADR-004` | Au plus trois Recommendation sont actives. | Une priorité principale reste visible sans liste infinie. |
| `ADV-ADR-005` | Le rang combine impact, urgence, confiance et effort. | L'ordre est explicable et stable. |
| `ADV-ADR-006` | Le comportement utilisateur n'influence pas le score 1.0. | Aucune personnalisation cachée ou boucle de biais. |
| `ADV-ADR-007` | La confiance est qualitative. | Elle ne peut pas être lue comme probabilité calibrée. |
| `ADV-ADR-008` | L'impact attendu est qualitatif. | Aucun gain monétaire ou causal n'est promis. |
| `ADV-ADR-009` | Une Recommendation possède une seule action. | L'utilisateur sait immédiatement où commencer. |
| `ADV-ADR-010` | L'action est une navigation allowlistée. | Advisor n'exécute aucune commande source. |
| `ADV-ADR-011` | Le domaine cible réautorise toujours l'utilisateur. | Une permission Advisor ne contourne jamais CRM ou Billing. |
| `ADV-ADR-012` | Une source identique réaffirme via une nouvelle EvidenceRevision. | La preuve courante évolue sans effacer l'historique. |
| `ADV-ADR-013` | Un changement matériel crée une nouvelle Recommendation. | Une identité ne change pas silencieusement de sens. |
| `ADV-ADR-014` | Completed remplace Executed. | Advisor confirme une action humaine sans prétendre l'avoir exécutée. |
| `ADV-ADR-015` | Completed ne constitue pas un outcome. | Adoption et effet métier restent distincts. |
| `ADV-ADR-016` | Displayed, Opened et Clicked quittent le domaine. | Product Analytics possède la télémétrie d'interface. |
| `ADV-ADR-017` | Une source tardive ne modifie pas le présent. | L'ordre de livraison ne change pas la priorité courante. |
| `ADV-ADR-018` | Une évaluation durable existe même sans candidat. | L'absence de recommandation reste auditée et explicable. |
| `ADV-ADR-019` | Une Recommendation terminale n'est jamais réactivée. | Un nouveau contexte reçoit une nouvelle identité. |
| `ADV-ADR-020` | Notifications réagit uniquement à AdvisorOverviewChanged. | Évaluation et mutations terminales empruntent la même convergence stabilisée. |
| `ADV-ADR-021` | AdvisorOverviewVersion ordonne chaque cause de convergence appliquée. | Les consommateurs refusent une publication livrée hors ordre, même après un changement de politique. |
| `ADV-ADR-022` | Une source courante insuffisante ou obsolète invalide les Recommendations actives. | L'overview devient vide et aucun conseil ancien ne reste présenté comme courant. |
| `ADV-ADR-023` | Toute mutation terminale reconstruit l'overview. | L'alternative suivante est promue et Notifications converge sans branche terminale distincte. |
