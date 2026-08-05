# Context Map

## Identity

Responsable :

- utilisateurs et authentification ;
- memberships et invitations ;
- rôles, permissions et décisions d'autorisation.

Consomme le contexte public d'accès de Workspace.

---

## Workspace

Responsable :

- identité de l'activité ;
- profil commercial et identité de facturation déclarée ;
- préférences principales ;
- cycle de vie et état d'accès du Workspace.

Consomme une preuve minimale d'owner actif fournie par Identity pour
l'activation et la restauration.

Identity et Workspace forment un partenariat contractuel explicite. Aucun des
deux ne lit le stockage ou ne modifie le modèle de l'autre.

---

## CRM

Responsable :

- clients ;
- contacts ;
- opportunités ;
- activités commerciales.

Le pipeline est une projection des opportunités. CRM produit les faits
commerciaux et fournit à Billing des contextes Client et Opportunity versionnés.

---

## Billing

Responsable :

- devis ;
- factures ;
- paiements manuels ;
- avoirs ;
- numérotation, soldes, échéances et artefacts financiers immuables.

Consomme les contextes publics Client et Opportunity de CRM et l'identité de
facturation de Workspace, puis en crée des snapshots sans modifier leurs
sources.

Produit les événements financiers.

---

## Analytics

Responsable :

- faits analytiques normalisés ;
- définitions et séries de métriques ;
- agrégations déterministes ;
- fraîcheur et complétude ;
- snapshots cohérents destinés à Business Health.

Consomme les événements puis les faits versionnés de CRM et Billing. Ne modifie
jamais leurs agrégats et ne produit ni prédiction, ni Recommendation.

---

## Business Health

Responsable :

- évaluations immuables de la santé récente de l'activité ;
- politique versionnée de score et de couverture ;
- facteurs, bandes, tendances et risques interprétés ;
- fiabilité, preuves et zone d'attention principale.

Consomme des AnalyticsSnapshot versionnés et cohérents. Ne redéfinit pas les
formules de métriques et ne lit pas les stockages CRM ou Billing. Ne produit ni
Recommendation, ni action, ni prédiction.

---

## Advisor

Responsable :

- politique versionnée de génération et de rang ;
- évaluations déterministes des sources Business Health ;
- recommandations, preuves, priorités et actions principales ;
- cycle Completed, Dismissed ou Expired ;
- priorité principale et alternatives courantes.

Consomme BusinessHealthAssessed puis l'évaluation exacte. Ne lit ni Analytics,
CRM ou Billing en 1.0 et n'exécute aucune action à la place de l'utilisateur.

---

## Notifications

Diffuse les événements importants.

Ne prend aucune décision métier à la place du domaine source.
