<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atlas Playground</title>
    <link rel="stylesheet" href="{{ asset('playground-assets/playground.css') }}">
</head>
<body>
    <header>
        <div>
            <h1>Atlas Playground</h1>
            <p>Interface de test API — local uniquement</p>
        </div>
        <div class="session-pills" id="sessionPills"></div>
    </header>

    <div class="layout">
        <div class="panel panel-actions">
            <div class="hero-actions">
                <h2>Parcours automatique</h2>
                <p>Inscription → workspace → CRM → devis → acceptation → facture → paiement</p>
                <button type="button" id="btnFullFlow">▶ Parcours MVP-J2 complet</button>
            </div>

            <details class="section" open>
                <summary>1. Onboarding</summary>
                <div class="section-body">
                    <div class="field"><label>Email</label><input id="email" type="email"></div>
                    <div class="field"><label>Mot de passe</label><input id="password" type="text"></div>
                    <div class="field"><label>Nom affiché</label><input id="displayName" type="text"></div>
                    <div class="field"><label>Nom workspace</label><input id="workspaceName" type="text"></div>
                    <div class="row">
                        <button type="button" id="btnRegister">Register + verify</button>
                        <button type="button" id="btnLogin" class="secondary">Login</button>
                        <button type="button" id="btnWorkspace" class="secondary">Créer workspace</button>
                    </div>
                </div>
            </details>

            <details class="section" open>
                <summary>2. CRM</summary>
                <div class="section-body">
                    <div class="field"><label>Client</label><input id="clientName" type="text"></div>
                    <div class="field"><label>Opportunité</label><input id="opportunityTitle" type="text"></div>
                    <div class="field"><label>Montant (centimes)</label><input id="amountCents" type="number" min="0"></div>
                    <div class="row">
                        <button type="button" id="btnClient">Créer client</button>
                        <button type="button" id="btnOpportunity" class="secondary">Créer opportunité</button>
                        <button type="button" id="btnQualify" class="secondary">Qualifier</button>
                        <button type="button" id="btnRefreshOpp" class="secondary">↻ Opportunité</button>
                        <button type="button" id="btnPipeline" class="secondary">Pipeline</button>
                    </div>
                </div>
            </details>

            <details class="section" open>
                <summary>3. Billing</summary>
                <div class="section-body">
                    <div class="field"><label>Ligne devis</label><input id="lineDescription" type="text"></div>
                    <div class="field"><label>Token acceptation publique</label><textarea id="publicAcceptToken" rows="2" placeholder="Rempli après envoi du devis"></textarea></div>
                    <div class="row">
                        <button type="button" id="btnQuote">Créer devis</button>
                        <button type="button" id="btnSendQuote" class="secondary">Envoyer devis</button>
                        <button type="button" id="btnAcceptQuote" class="secondary">Accepter (public)</button>
                        <button type="button" id="btnOutbox" class="secondary">Process outbox</button>
                    </div>
                    <div class="row">
                        <button type="button" id="btnInvoice">Facture depuis devis</button>
                        <button type="button" id="btnIssue" class="secondary">Émettre</button>
                        <button type="button" id="btnPayment" class="secondary">Enregistrer paiement</button>
                    </div>
                </div>
            </details>

            <details class="section">
                <summary>4. Analytics</summary>
                <div class="section-body">
                    <div class="row">
                        <button type="button" id="btnPublishSnapshot">Publier snapshot</button>
                        <button type="button" id="btnLatestSnapshot" class="secondary">↻ Snapshot latest</button>
                        <button type="button" id="btnPipelineMetric" class="secondary">Métrique pipeline</button>
                    </div>
                </div>
            </details>

            <details class="section">
                <summary>5. Business Health</summary>
                <div class="section-body">
                    <div class="row">
                        <button type="button" id="btnCurrentHealth">Santé courante</button>
                        <button type="button" id="btnHealthAssessment" class="secondary">↻ Détail évaluation</button>
                    </div>
                </div>
            </details>

            <details class="section">
                <summary>6. Advisor</summary>
                <div class="section-body">
                    <div class="row">
                        <button type="button" id="btnAdvisorOverview">Overview recommandations</button>
                    </div>
                </div>
            </details>

            <details class="section">
                <summary>7. Notifications</summary>
                <div class="section-body">
                    <div class="row">
                        <button type="button" id="btnUnreadCount">Compteur non lu</button>
                        <button type="button" id="btnNotifications" class="secondary">Liste inbox</button>
                    </div>
                </div>
            </details>

            <details class="section">
                <summary>8. Dashboard</summary>
                <div class="section-body">
                    <div class="row">
                        <button type="button" id="btnDashboard">Vue dashboard</button>
                    </div>
                </div>
            </details>

            <details class="section">
                <div class="section-body">
                    <div class="row">
                        <button type="button" id="btnReset" class="danger">Reset session locale</button>
                    </div>
                </div>
            </details>
        </div>

        <div class="panel panel-log">
            <div class="log-toolbar">
                <h2>Journal API</h2>
                <button type="button" id="btnClearLog" class="secondary">Effacer</button>
            </div>
            <div id="log">
                <div class="empty-log">Aucun appel API pour l’instant.</div>
            </div>
        </div>
    </div>

    <script src="{{ asset('playground-assets/playground.js') }}"></script>
</body>
</html>
