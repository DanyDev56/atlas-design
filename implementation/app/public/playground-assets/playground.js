(() => {
    const API = '/api';
    const STORAGE_KEY = 'atlas_playground_state';

    const defaultState = () => ({
        email: `dev+${Date.now()}@atlas.test`,
        password: 'password123',
        displayName: 'Dev User',
        workspaceName: 'Playground Workspace',
        clientName: 'Acme Corp',
        opportunityTitle: 'Projet web',
        amountCents: 50000,
        lineDescription: 'Prestation',
        token: null,
        userId: null,
        workspaceId: null,
        clientId: null,
        opportunityId: null,
        opportunityVersion: 1,
        quoteId: null,
        quoteVersion: 1,
        publicAcceptToken: null,
        invoiceId: null,
        invoiceVersion: 1,
    });

    let state = loadState();

    const $ = (id) => document.getElementById(id);

    function loadState() {
        try {
            return { ...defaultState(), ...JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') };
        } catch {
            return defaultState();
        }
    }

    function saveState() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        renderSession();
        syncForms();
    }

    function uuid() {
        return crypto.randomUUID();
    }

    function syncForms() {
        const map = {
            email: state.email,
            password: state.password,
            displayName: state.displayName,
            workspaceName: state.workspaceName,
            clientName: state.clientName,
            opportunityTitle: state.opportunityTitle,
            amountCents: state.amountCents,
            lineDescription: state.lineDescription,
            publicAcceptToken: state.publicAcceptToken || '',
        };
        Object.entries(map).forEach(([id, value]) => {
            const el = $(id);
            if (el) el.value = value;
        });
    }

    function renderSession() {
        const pills = [
            ['Token', !!state.token],
            ['Workspace', !!state.workspaceId],
            ['Client', !!state.clientId],
            ['Opportunity', !!state.opportunityId],
            ['Quote', !!state.quoteId],
            ['Invoice', !!state.invoiceId],
        ];
        $('sessionPills').innerHTML = pills.map(([label, ok]) =>
            `<span class="pill ${ok ? 'ok' : ''}">${label}</span>`
        ).join('');
    }

    function log(method, path, status, body, ok) {
        const container = $('log');
        const empty = container.querySelector('.empty-log');
        if (empty) empty.remove();

        const entry = document.createElement('div');
        entry.className = `log-entry ${ok ? 'ok' : 'err'}`;
        entry.innerHTML = `
            <div class="meta">
                <span class="method">${method}</span>
                <span>${path}</span>
                <span class="status">${status}</span>
                <span>${new Date().toLocaleTimeString()}</span>
            </div>
            <pre>${escapeHtml(JSON.stringify(body, null, 2))}</pre>
        `;
        container.prepend(entry);
    }

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function readForm() {
        ['email', 'password', 'displayName', 'workspaceName', 'clientName', 'opportunityTitle', 'lineDescription'].forEach((id) => {
            const el = $(id);
            if (el) state[id] = el.value;
        });
        state.amountCents = parseInt($('amountCents')?.value || '50000', 10);
        state.publicAcceptToken = $('publicAcceptToken')?.value || state.publicAcceptToken;
        saveState();
    }

    async function api(method, path, body = null, auth = true) {
        const headers = {
            Accept: 'application/json',
            'Idempotency-Key': uuid(),
        };
        if (body !== null) headers['Content-Type'] = 'application/json';
        if (auth && state.token) headers.Authorization = `Bearer ${state.token}`;

        const res = await fetch(`${API}${path}`, {
            method,
            headers,
            body: body !== null ? JSON.stringify(body) : undefined,
        });

        let data;
        try {
            data = await res.json();
        } catch {
            data = { raw: await res.text() };
        }

        log(method, path, res.status, data, res.ok);
        if (!res.ok) throw new Error(data.messages?.[0] || data.error || `HTTP ${res.status}`);
        return data;
    }

    async function register() {
        readForm();
        const data = await api('POST', '/auth/register', {
            email: state.email,
            display_name: state.displayName,
            password: state.password,
            debug_verification_token: true,
        }, false);
        state.userId = data.user_id;
        if (data.verification_token) {
            await api('POST', '/auth/verify-email', {
                user_id: data.user_id,
                token: data.verification_token,
            }, false);
        }
        saveState();
    }

    async function login() {
        readForm();
        const data = await api('POST', '/auth/login', {
            email: state.email,
            password: state.password,
        }, false);
        state.token = data.token;
        state.userId = data.user_id;
        saveState();
    }

    async function createWorkspace() {
        const data = await api('POST', '/workspaces/first', { name: state.workspaceName });
        state.workspaceId = data.workspace_id;
        saveState();
    }

    async function createClient() {
        readForm();
        const data = await api('POST', `/workspaces/${state.workspaceId}/clients`, {
            kind: 'Organization',
            display_name: state.clientName,
            profile: { email: 'contact@acme.test' },
        });
        state.clientId = data.client_id;
        saveState();
    }

    async function createOpportunity() {
        readForm();
        const data = await api('POST', `/workspaces/${state.workspaceId}/opportunities`, {
            client_id: state.clientId,
            title: state.opportunityTitle,
            estimated_amount_cents: state.amountCents,
            currency: 'EUR',
        });
        state.opportunityId = data.opportunity_id;
        state.opportunityVersion = data.version;
        saveState();
    }

    async function qualifyOpportunity() {
        const data = await api('POST', `/workspaces/${state.workspaceId}/opportunities/${state.opportunityId}/qualify`, {
            expected_revision: state.opportunityVersion,
        });
        state.opportunityVersion = data.version;
        saveState();
    }

    async function createQuote() {
        readForm();
        const data = await api('POST', `/workspaces/${state.workspaceId}/quotes`, {
            client_id: state.clientId,
            opportunity_id: state.opportunityId,
            currency: 'EUR',
            lines: [{
                description: state.lineDescription,
                quantity: 1,
                unit_price_cents: state.amountCents,
            }],
        });
        state.quoteId = data.quote_id;
        state.quoteVersion = data.version;
        saveState();
    }

    async function sendQuote() {
        const data = await api('POST', `/workspaces/${state.workspaceId}/quotes/${state.quoteId}/send`, {
            expected_revision: state.quoteVersion,
        });
        state.quoteVersion = data.version;
        state.publicAcceptToken = data.public_accept_token;
        $('publicAcceptToken').value = state.publicAcceptToken;
        saveState();
    }

    async function acceptQuote() {
        readForm();
        const data = await api('POST', `/public/workspaces/${state.workspaceId}/quotes/${state.quoteId}/accept`, {
            public_token: state.publicAcceptToken,
            expected_revision: state.quoteVersion,
        }, false);
        state.quoteVersion = data.version;
        saveState();
    }

    async function processOutbox() {
        await api('POST', '/dev/outbox/process', null, false);
    }

    async function createInvoice() {
        const data = await api('POST', `/workspaces/${state.workspaceId}/quotes/${state.quoteId}/invoices`, {});
        state.invoiceId = data.invoice_id;
        state.invoiceVersion = data.version;
        saveState();
    }

    async function issueInvoice() {
        const data = await api('POST', `/workspaces/${state.workspaceId}/invoices/${state.invoiceId}/issue`, {
            expected_revision: state.invoiceVersion,
        });
        state.invoiceVersion = data.version;
        saveState();
    }

    async function recordPayment() {
        readForm();
        const data = await api('POST', `/workspaces/${state.workspaceId}/invoices/${state.invoiceId}/payments`, {
            amount_cents: state.amountCents,
            reference: 'VIR-PLAYGROUND',
        });
        state.invoiceVersion = data.version;
        saveState();
    }

    async function refreshOpportunity() {
        const data = await api('GET', `/workspaces/${state.workspaceId}/opportunities/${state.opportunityId}`);
        state.opportunityVersion = data.version;
        saveState();
        return data;
    }

    async function refreshPipeline() {
        return api('GET', `/workspaces/${state.workspaceId}/pipeline`);
    }

    async function publishSnapshot() {
        return api('POST', `/workspaces/${state.workspaceId}/analytics/snapshots/publish`, {});
    }

    async function latestSnapshot() {
        return api('GET', `/workspaces/${state.workspaceId}/analytics/snapshot/latest`);
    }

    async function pipelineMetric() {
        return api('GET', `/workspaces/${state.workspaceId}/analytics/metrics/analytics.pipeline.open-amount`);
    }

    async function runFullFlow() {
        const btn = $('btnFullFlow');
        btn.disabled = true;
        btn.textContent = 'Parcours en cours…';
        try {
            await register();
            await login();
            await createWorkspace();
            await createClient();
            await createOpportunity();
            await qualifyOpportunity();
            await createQuote();
            await sendQuote();
            await acceptQuote();
            await processOutbox();
            await refreshOpportunity();
            await createInvoice();
            await issueInvoice();
            await recordPayment();
            await refreshPipeline();
            await processOutbox();
            await publishSnapshot();
            await latestSnapshot();
        } finally {
            btn.disabled = false;
            btn.textContent = '▶ Parcours MVP-J2 complet';
        }
    }

    function bind(id, fn) {
        $(id)?.addEventListener('click', async () => {
            readForm();
            try {
                await fn();
            } catch (e) {
                log('ERR', id, '—', { message: e.message }, false);
            }
        });
    }

    bind('btnRegister', register);
    bind('btnLogin', login);
    bind('btnWorkspace', createWorkspace);
    bind('btnClient', createClient);
    bind('btnOpportunity', createOpportunity);
    bind('btnQualify', qualifyOpportunity);
    bind('btnQuote', createQuote);
    bind('btnSendQuote', sendQuote);
    bind('btnAcceptQuote', acceptQuote);
    bind('btnOutbox', processOutbox);
    bind('btnInvoice', createInvoice);
    bind('btnIssue', issueInvoice);
    bind('btnPayment', recordPayment);
    bind('btnRefreshOpp', refreshOpportunity);
    bind('btnPipeline', refreshPipeline);
    bind('btnPublishSnapshot', publishSnapshot);
    bind('btnLatestSnapshot', latestSnapshot);
    bind('btnPipelineMetric', pipelineMetric);

    $('btnFullFlow')?.addEventListener('click', () => runFullFlow().catch((e) => {
        log('ERR', 'full-flow', '—', { message: e.message }, false);
    }));

    $('btnClearLog')?.addEventListener('click', () => {
        $('log').innerHTML = '<div class="empty-log">Aucun appel API pour l’instant.</div>';
    });

    $('btnReset')?.addEventListener('click', () => {
        if (!confirm('Effacer la session locale ?')) return;
        state = defaultState();
        saveState();
        $('log').innerHTML = '<div class="empty-log">Aucun appel API pour l’instant.</div>';
    });

    renderSession();
    syncForms();
})();
