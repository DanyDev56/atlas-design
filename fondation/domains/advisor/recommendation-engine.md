# Recommendation Engine

Une recommandation est produite lorsqu'une combinaison de signaux atteint un seuil défini.

Une recommandation possède toujours :

- une cause ;
- un objectif ;
- une priorité ;
- un impact estimé ;
- une confiance ;
- une action.

---

## Sources

Advisor consomme en priorité :

- les `AnalyticsSnapshot` publiés ;
- les `BusinessHealthAssessment` publiées, notamment leurs facteurs, tendances,
  risques et PrimaryAttention ;
- des faits publics ciblés lorsque l'action doit référencer une ressource ;
- à terme, les contextes projets et calendrier explicitement contractés.

Il ne relit pas les stockages CRM ou Billing et ne recalcule pas leurs agrégats.
Une Recommendation peut citer plusieurs sources versionnées, mais sa preuve doit
conserver les métriques, périodes, fraîcheur et complétude utilisées.

Après `BusinessHealthAssessed`, Advisor relit la source exacte par :

```text
getBusinessHealthAssessment(workspaceId, businessHealthAssessmentId)
```

avec `business-health.assessments.consume`. Il vérifie status, fiabilité,
HealthPolicyVersion et AsOf avant de transformer une zone d'attention en action.
