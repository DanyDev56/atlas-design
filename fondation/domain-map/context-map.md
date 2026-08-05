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

- indicateurs ;
- agrégations ;
- statistiques.

Ne crée jamais de données métier.

---

## Business Health

Calcule l'état global de l'entreprise.

Consomme les données de plusieurs domaines.

---

## Advisor

Produit des recommandations.

Ne possède aucune donnée métier.

---

## Notifications

Diffuse les événements importants.

Ne prend aucune décision métier à la place du domaine source.
