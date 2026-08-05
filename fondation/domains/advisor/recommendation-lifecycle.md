---
id: ADV-RECOMMENDATION-LIFECYCLE
title: Recommendation Lifecycle
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - invariants.md
  - commands/README.md
  - processors/README.md
  - events.md
---

# Cycle de vie d'une Recommendation

```text
Candidate (transient)
        │ policy satisfied
        ▼
    Generated
      ├──► Completed
      ├──► Dismissed
      └──► Expired
```

`Candidate` est un résultat transitoire de RecommendationEvaluation, sans
identité métier. `Generated` est le seul état actif. Les trois autres sont
terminaux et mutuellement exclusifs.

## Generated

La Recommendation a satisfait la politique, possède une preuve, une
explication, une priorité, une fenêtre de validité et une action principale.

Une nouvelle source avec le même TriggerFingerprint peut ajouter une
EvidenceRevision et produire `RecommendationReaffirmed`. L'état reste Generated
et l'historique des preuves précédentes est conservé.

## Completed

L'utilisateur confirme avoir accompli l'action principale. Cette confirmation :

- ne prouve pas que CRM ou Billing a été modifié ;
- ne prouve aucun résultat ou impact causal ;
- n'est pas appelée Executed ;
- ne peut pas être annulée ou transformée en un autre état terminal.

## Dismissed

L'utilisateur rejette explicitement la Recommendation avec un motif structuré.
Advisor respecte ce choix et ne régénère pas le même TriggerFingerprint tant
que le prédicat n'a pas disparu ou matériellement changé.

## Expired

La Recommendation expire lorsque :

- `ValidUntil` est atteint ;
- une nouvelle BusinessHealthAssessment invalide son prédicat ;
- elle sort des trois priorités publiables ;
- son TriggerFingerprint change matériellement ;
- sa RecommendationPolicyVersion est remplacée.

## Interactions produit

Affichage, ouverture et clic ne sont ni des états ni des Domain Events Advisor.
Ils appartiennent à Product Analytics. Ignorer une recommandation ne vaut pas
Dismissed sans intention explicite.

## Nouvelle Recommendation

Une Recommendation terminale n'est jamais réactivée. Une nouvelle identité est
autorisée uniquement après disparition puis retour du prédicat, ou après un
TriggerFingerprint matériellement nouveau. La causalité peut référencer la
Recommendation précédente.
