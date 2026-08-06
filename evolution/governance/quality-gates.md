# Quality Gates

Une fonctionnalité ne peut être publiée que si tous les critères suivants sont validés.

## Métier

- Les règles métier sont documentées.
- Les invariants sont respectés.
- Les événements sont définis.

---

## UX

- Les parcours utilisateur sont validés.
- Les textes respectent le Product Language.
- Les états d'erreur sont prévus.

---

## Technique

- Tests unitaires.
- Tests d'intégration.
- Migration réversible.
- Journalisation.
- Monitoring.

---

## Produit

- La fonctionnalité respecte la Constitution.
- Elle renforce au moins une Capability.
- Elle possède des métriques de succès.
## Gate documentaire automatisé

Toute pull request exécute `bash scripts/check-all.sh`. Le gate vérifie les liens
locaux, l'unicité des IDs, le registre des RouteKeys, les contrats spécialisés de
chaque domaine et les erreurs de whitespace. Il est bloquant pour `main` et ne
requiert aucune dépendance réseau après le checkout.

Une modification de contrat met à jour dans le même commit les producteurs,
consommateurs, cartes globales et ADR concernés. Désactiver un contrôle nécessite
un ADR accepté ; une exception temporaire possède propriétaire et échéance.
