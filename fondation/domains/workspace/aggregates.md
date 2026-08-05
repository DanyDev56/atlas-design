---
id: WSP-AGGREGATES
title: Workspace Aggregates
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - model.md
  - entities.md
  - value-objects.md
  - invariants.md
  - workflows.md
---

# Agrégats

## Agrégat Workspace

| Élément | Décision 1.0 |
|---|---|
| Racine | `Workspace` |
| Frontière transactionnelle | une instance de `Workspace` |
| Concurrence | `ExpectedRevision` obligatoire sur les mutations |
| Persistance des faits | état et événements atomiques |

L'agrégat contient uniquement :

- `WorkspaceProfile` ;
- `BillingIdentity` ;
- `WorkspacePreferences` ;
- le contexte éventuel de restriction ou de fermeture ;
- les versions et instants nécessaires à ses invariants.

---

## Règles transactionnelles

- une commande modifie au plus un Workspace ;
- un changement et ses Domain Events sont atomiques ;
- une lecture externe n'est jamais conservée comme objet mutable dans
  l'agrégat ;
- une preuve inter-domaine contient une portée, une version et une échéance ;
- un effet externe est publié via une outbox transactionnelle.

Le bootstrap coordonne Workspace et Identity, mais ne les transforme pas en un
agrégat distribué. Chaque étape possède son commit et sa reprise idempotente.

---

## Invariants transverses

L'activation et la restauration exigent un owner actif dans `Identity`. Cette
règle est vérifiée au moyen d'une preuve de readiness fournie par le contrat
public d'Identity.

```text
Identity state --readiness proof--> Workspace command
                                      |
                                      v
                              local invariant check
                                      |
                                      v
                                  local commit
```

La preuve ne permet jamais à Workspace de modifier un `Membership` ou un
`Role`.

---

## Pourquoi un seul agrégat

Le profil, les préférences et le cycle de vie sont petits, peu conflictuels et
doivent rester cohérents avec les versions publiques. Les séparer en agrégats
1.0 créerait une coordination sans bénéfice métier établi.

Une extraction future exige une décision explicite et des contrats de migration.
