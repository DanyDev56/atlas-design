# Domain Events

## Événements de cycle de vie

| Événement | Signification |
|---|---|
| `RecommendationGenerated` | Une recommandation a satisfait les règles de génération. |
| `RecommendationExecuted` | L'action principale a été accomplie ou confirmée. |
| `RecommendationDismissed` | L'utilisateur a explicitement rejeté la recommandation. |
| `RecommendationExpired` | Le contexte ou la fenêtre d'action n'est plus valide. |

`RecommendationExecuted`, `RecommendationDismissed` et
`RecommendationExpired` correspondent à des transitions terminales exclusives.

---

## Événements d'interaction

| Événement | Signification |
|---|---|
| `RecommendationDisplayed` | La recommandation a été affichée à l'utilisateur. |
| `RecommendationOpened` | L'utilisateur a consulté son détail. |

Ces événements ne changent pas l'état métier de la recommandation.

---

## Régénération

Une recommandation terminale n'est jamais réactivée.

Lorsqu'un nouveau fait justifie une action similaire, Atlas crée une nouvelle
`Recommendation` et produit `RecommendationGenerated`. La relation avec une
recommandation antérieure est portée par une référence de causalité, et non par
un événement `RecommendationRegenerated` ambigu.
