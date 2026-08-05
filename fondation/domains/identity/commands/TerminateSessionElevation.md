---
id: IDN-CMD-TERMINATE-SESSION-ELEVATION
title: TerminateSessionElevation
status: In Review
owner: Product
version: 1.0.0
last_updated: 2026-08-05

aggregate: Session

invariants:
  - IDN-INV-010
  - IDN-INV-012
  - IDN-INV-022

references:
  - README.md
  - ../entities.md
  - ../value-objects.md
  - ../invariants.md
  - ../events.md
  - ElevateSession.md
  - ExpireSessionElevation.md
  - RevokeSession.md
---

# TerminateSessionElevation

## Objectif

`TerminateSessionElevation` met fin avant son échéance à une élévation de
session, sans révoquer nécessairement la session principale.

```text
Active Elevation -> Terminated Elevation
```

---

## Agrégat

`Session`.

---

## Acteur

- le sujet mettant volontairement fin à sa propre élévation ;
- un workflow de sécurité ou de risque de confiance ;
- le moteur consommant la dernière action d'une élévation bornée.

Aucun rôle de workspace ne peut terminer l'élévation d'un autre sujet.

---

## Données d'entrée

- `SessionId` ;
- `ElevationVersion` ;
- `TerminationReason` ;
- `TerminatedBy` ;
- `TerminatedAt` ;
- `TerminateSessionElevationRequestId` ;
- `ExpectedSessionVersion` ;
- `CorrelationId`.

`TerminationReason` appartient à un catalogue fermé : `SubjectRequest`,
`ScopeConsumed`, `SecurityStateChanged`, `RiskChanged`, `SessionRevocation` ou
`WorkflowCompleted`.

---

## Préconditions

- la `Session` et l'élévation existent ;
- l'élévation est encore active ;
- l'acteur ou le workflow est autorisé pour le motif déclaré ;
- la version d'élévation correspond ;
- une preuve ou un signal de confiance couvre cette terminaison lorsque requis.

---

## Traitement métier

1. Recharger la session et son élévation sous concurrence optimiste.
2. Valider l'autorité et le motif.
3. Définir `SessionElevationStatus = Terminated` et la rendre inutilisable.
4. Incrémenter `SessionSecurityVersion` et la version d'agrégat.
5. Enregistrer `SessionElevationTerminated` dans le même commit.

La commande ne retire aucune permission et ne modifie pas le `User`, le
`Membership` ou le `Role`.

---

## Événement produit

`SessionElevationTerminated`.

---

## Erreurs métier

- `SessionNotFound` ;
- `SessionElevationNotFound` ;
- `SessionElevationAlreadyTerminal` ;
- `SessionElevationTerminationNotAuthorized` ;
- `SessionElevationTerminationProofInvalid` ;
- `SessionElevationVersionConflict` ;
- `SessionVersionConflict` ;
- `IdempotencyConflict`.

---

## Idempotence

La clé est `TerminateSessionElevationRequestId`. Un retry identique retourne le
résultat initial sans nouvel événement.
