# Invariants

Les règles suivantes sont absolues.

Elles ne doivent jamais être violées.

---

## Une facture émise ne redevient jamais un brouillon.

---

## Le contenu financier d’une facture émise est immuable.

Après émission, il est interdit de modifier directement :

- les lignes ;
- les quantités ;
- les prix ;
- les taux de TVA ;
- le client facturé ;
- le numéro ;
- la devise.

Les métadonnées non fiscales peuvent être corrigées lorsqu’elles ne modifient pas la portée juridique du document.

Toute correction financière passe par un avoir ou un document de remplacement.

---

## Chaque facture possède un numéro unique.

---

## Un paiement peut dépasser le solde attendu uniquement si le trop-perçu est explicitement enregistré.

Le système doit distinguer :

- le montant affecté à la facture ;
- le montant non affecté ;
- le montant à rembourser ou à utiliser comme crédit client.

---

## Un paiement appartient à une seule facture.

---

## Une facture annulée conserve son historique.

---

## Toute action importante génère un événement métier.

---

## Chaque changement est traçable.

Aucune suppression physique des événements métier.

---

## Les montants sont immuables.

Une correction crée un nouvel événement.

On ne modifie jamais l'historique.