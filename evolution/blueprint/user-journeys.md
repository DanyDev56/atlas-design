---
id: BPT-002
title: MVP User Journeys
status: In Review
owner: Product
version: 1.1.0
last_updated: 2026-08-06

references:
  - README.md
  - lifecycle.md
  - navigation.md
  - ../roadmap/mvp-scope.md
  - ../roadmap/mvp-acceptance.md
  - ../../fondation/product/personnas/persona-primary.md
  - ../../fondation/product/jobs-to-be-done/manage-business.md
---

# Parcours utilisateur du MVP

Ces parcours décrivent l'expérience visible. Leur trace contractuelle et leurs
cas d'échec normatifs sont définis dans
[`mvp-acceptance.md`](../roadmap/mvp-acceptance.md).

---

## MVP-J1 — Commencer avec Atlas

```text
S'inscrire
  -> vérifier son adresse
  -> ouvrir une session
  -> renseigner son activité
  -> attendre le provisioning explicite
  -> entrer dans un Workspace actif
  -> choisir une saisie neuve ou importer son historique
```

L'utilisateur voit la progression du provisioning. Il ne peut pas entrer dans
une application partiellement autorisée. Si une étape échoue, Atlas reprend le
même workflow et n'impose ni nouveau compte ni nouveau Workspace.

Pour un utilisateur établi, l'import guidé enchaîne Clients, Quotes, Invoices et
Payments. Atlas préserve numéros, dates et états historiques, mais ne rejoue
aucun envoi, aucune émission, aucune communication ni aucune numérotation.

Résultat : une identité active, un Workspace actif et un membership owner
cohérents, puis une première vue utile issue de données réelles. Si la couverture
reste insuffisante, Atlas montre le chemin de progression au lieu d'inventer un
score ou une priorité.

---

## MVP-J2 — Transformer une opportunité en paiement

```text
Créer un client
  -> créer et qualifier une opportunité
  -> préparer et envoyer un devis
  -> laisser le client le consulter et l'accepter
  -> constater l'opportunité gagnée
  -> créer, émettre et envoyer la facture
  -> enregistrer un ou plusieurs paiements
  -> constater le solde
```

Le client externe n'a pas besoin de compte Atlas : il agit avec une preuve
publique bornée au document. Le prestataire conserve un historique financier
immuable ; une correction ajoute une nouvelle décision au lieu d'effacer la
précédente.

Résultat : le cycle commercial et le solde racontent la même histoire sans
partager leurs modèles internes.

---

## MVP-J3 — Comprendre et décider

```text
Enregistrer des faits CRM et Billing
  -> attendre leur projection mesurée
  -> consulter la santé de l'activité
  -> comprendre ses preuves et sa fraîcheur
  -> recevoir une priorité Advisor lorsqu'elle est justifiée
  -> être notifié selon ses préférences
  -> agir dans le module propriétaire
  -> constater le prochain cycle de mesure
```

Atlas distingue explicitement :

- une absence de données ;
- des données insuffisantes ;
- des données suffisantes sans recommandation ;
- une recommandation active ;
- une dépendance temporairement indisponible.

Résultat : l'utilisateur reçoit une décision explicable, jamais une certitude
inventée. L'action proposée ouvre CRM ou Billing, qui réautorise et exécute sa
propre commande.

---

## Continuité entre les parcours

Les trois parcours forment une seule boucle :

```text
MVP-J1 : établir un contexte sûr
             |
             v
MVP-J2 : produire des faits utiles
             |
             v
MVP-J3 : comprendre, décider et agir
             |
             +----> nouveaux faits mesurables
```

Le Dashboard rend cette continuité visible, mais ne devient propriétaire
d'aucune étape.
