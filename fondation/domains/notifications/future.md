---
id: NTF-FUTURE
title: Notifications Future Extensions
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

references:
  - scope.md
  - notification-policy.md
  - decision-record.md
---

# Extensions futures

Ces capacités ne modifient aucun contrat Notifications 1.0 :

- nouvelles sources produit après contrat d'éligibilité et de thread propre ;
- préférences par topic et par niveau d'importance ;
- quiet hours tenant compte du fuseau de l'utilisateur ;
- digest explicite avec politique de regroupement déterministe ;
- push mobile ou navigateur avec consentement et endpoint opaques ;
- SMS uniquement pour un besoin validé, un opt-in séparé et un coût borné ;
- centre de notifications multi-Workspace explicitement autorisé ;
- délégation administrative limitée sans accès implicite au contenu personnel ;
- politique de rétention et d'effacement compatible avec les obligations légales ;
- accessibilité et préférences de rendu indépendantes du contenu source ;
- détection de fatigue fondée sur des mesures produit anonymisées ;
- mesure d'efficacité séparant exposition, clic, adoption et résultat métier ;
- fournisseur secondaire avec règles de bascule sans double envoi ;
- templates expérimentaux versionnés et contrôlés ;
- automatisations préparées mais jamais exécutées sans consentement propre.

Toute extension doit définir source autoritaire, audience, consentement,
confidentialité, déduplication, fréquence, cycle de vie, preuve de livraison,
coût, permissions, reprise, rétention et stratégie de rollback.

Ajouter une source ne transforme jamais Notifications en bus générique : chaque
topic doit disposer d'un événement stabilisé, d'une lecture exacte et d'une
politique versionnée.
