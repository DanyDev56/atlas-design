<?php

declare(strict_types=1);

namespace Atlas\Composition\Demo;

use Atlas\Composition\Onboarding\BootstrapFirstWorkspaceHandler;
use Atlas\Modules\Analytics\Application\PublishAnalyticsSnapshotHandler;
use Atlas\Modules\Billing\Application\AcceptQuoteHandler;
use Atlas\Modules\Billing\Application\CreateFinalInvoiceFromQuoteHandler;
use Atlas\Modules\Billing\Application\CreateQuoteHandler;
use Atlas\Modules\Billing\Application\IssueInvoiceHandler;
use Atlas\Modules\Billing\Application\RecordPaymentHandler;
use Atlas\Modules\Billing\Application\SendInvoiceHandler;
use Atlas\Modules\Billing\Application\SendQuoteHandler;
use Atlas\Modules\Crm\Application\AddContactHandler;
use Atlas\Modules\Crm\Application\CreateClientHandler;
use Atlas\Modules\Crm\Application\CreateOpportunityHandler;
use Atlas\Modules\Crm\Application\QualifyOpportunityHandler;
use Atlas\Modules\Crm\Application\RecordActivityHandler;
use Atlas\Modules\Identity\Application\RegisterUserHandler;
use Atlas\Modules\Identity\Application\VerifyUserEmailHandler;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;

final class DemoAccountSeeder
{
    public const SCENARIO_VERSION = '3';

    public const EMAIL = 'demo@atlas.test';

    public const PASSWORD = 'DemoAtlas2026!';

    public const DISPLAY_NAME = 'Présentation Atlas';

    public const WORKSPACE_NAME = 'Studio Atlas Démo';

    public const EMPTY_SCENARIO_VERSION = '1';

    public const EMPTY_EMAIL = 'demo-empty@atlas.test';

    public const EMPTY_PASSWORD = 'DemoEmpty2026!';

    public const EMPTY_DISPLAY_NAME = 'Découverte Atlas';

    public const EMPTY_WORKSPACE_NAME = 'Nouvelle activité démo';

    private const EXPECTED_CLIENT_NAMES = [
        'Les Ateliers du Marais',
        'Horizon Digital',
        'Maison Lumen',
        'Nova Conseil',
        'Cabinet Rivoli',
        'Collectif Cobalt',
    ];

    private const CONTACTS_BY_CLIENT = [
        'Les Ateliers du Marais' => [
            ['display_name' => 'Camille Martin', 'email' => 'camille@ateliers-marais.test', 'phone' => '06 18 24 42 10', 'role' => 'Direction'],
            ['display_name' => 'Julien Morel', 'email' => 'julien@ateliers-marais.test', 'role' => 'Chef de projet'],
        ],
        'Horizon Digital' => [
            ['display_name' => 'Sarah Benali', 'email' => 'sarah@horizon-digital.test', 'role' => 'Responsable technique'],
        ],
        'Maison Lumen' => [
            ['display_name' => 'Élise Garnier', 'email' => 'elise@maison-lumen.test', 'role' => 'Fondatrice'],
        ],
        'Nova Conseil' => [
            ['display_name' => 'Nicolas Aubert', 'email' => 'nicolas@nova-conseil.test', 'role' => 'Associé'],
        ],
        'Cabinet Rivoli' => [
            ['display_name' => 'Claire Durand', 'email' => 'claire@cabinet-rivoli.test', 'role' => 'Office manager'],
        ],
        'Collectif Cobalt' => [
            ['display_name' => 'Lina Perez', 'email' => 'lina@collectif-cobalt.test', 'role' => 'Production'],
        ],
    ];

    private const ACTIVITIES = [
        ['client' => 'Les Ateliers du Marais', 'kind' => 'Meeting', 'summary' => 'Atelier de cadrage terminé : identité plus éditoriale et lancement prévu au prochain trimestre.', 'days_ago' => 12, 'contact_email' => 'camille@ateliers-marais.test', 'opportunity' => 'Refonte identité visuelle'],
        ['client' => 'Les Ateliers du Marais', 'kind' => 'Call', 'summary' => 'Validation téléphonique du périmètre de l’audit express.', 'days_ago' => 4, 'contact_email' => 'julien@ateliers-marais.test', 'opportunity' => 'Audit express à qualifier'],
        ['client' => 'Horizon Digital', 'kind' => 'Email', 'summary' => 'Confirmation reçue : la migration cloud est terminée et la facture a été réglée.', 'days_ago' => 5, 'contact_email' => 'sarah@horizon-digital.test', 'opportunity' => 'Migration cloud'],
        ['client' => 'Maison Lumen', 'kind' => 'Call', 'summary' => 'Échange sur le devis envoyé ; retour attendu après validation avec l’équipe fondatrice.', 'days_ago' => 2, 'contact_email' => 'elise@maison-lumen.test', 'opportunity' => 'Lancement e-commerce'],
        ['client' => 'Nova Conseil', 'kind' => 'Meeting', 'summary' => 'Présentation de la plateforme de marque acceptée lors du comité de direction.', 'days_ago' => 7, 'contact_email' => 'nicolas@nova-conseil.test', 'opportunity' => 'Positionnement de marque'],
        ['client' => 'Cabinet Rivoli', 'kind' => 'Note', 'summary' => 'Le devis est accepté ; la facture brouillon doit être vérifiée avant émission.', 'days_ago' => 3, 'contact_email' => 'claire@cabinet-rivoli.test', 'opportunity' => 'Nouveau site vitrine'],
        ['client' => 'Collectif Cobalt', 'kind' => 'Call', 'summary' => 'Relance amiable effectuée après l’acompte ; le solde reste à régulariser.', 'days_ago' => 1, 'contact_email' => 'lina@collectif-cobalt.test', 'opportunity' => 'Campagne annuelle'],
        ['client' => 'Collectif Cobalt', 'kind' => 'Email', 'summary' => 'Accusé de réception de la facture obtenu auprès de la production.', 'days_ago' => 9, 'contact_email' => 'lina@collectif-cobalt.test', 'opportunity' => null],
    ];

    public function __construct(
        private readonly PostgresUserRepository $users,
        private readonly RegisterUserHandler $registerUser,
        private readonly VerifyUserEmailHandler $verifyEmail,
        private readonly BootstrapFirstWorkspaceHandler $bootstrapWorkspace,
        private readonly CreateClientHandler $createClient,
        private readonly AddContactHandler $addContact,
        private readonly CreateOpportunityHandler $createOpportunity,
        private readonly QualifyOpportunityHandler $qualifyOpportunity,
        private readonly RecordActivityHandler $recordActivity,
        private readonly CreateQuoteHandler $createQuote,
        private readonly SendQuoteHandler $sendQuote,
        private readonly AcceptQuoteHandler $acceptQuote,
        private readonly CreateFinalInvoiceFromQuoteHandler $createInvoiceFromQuote,
        private readonly IssueInvoiceHandler $issueInvoice,
        private readonly SendInvoiceHandler $sendInvoice,
        private readonly RecordPaymentHandler $recordPayment,
        private readonly PublishAnalyticsSnapshotHandler $publishSnapshot,
        private readonly OutboxProcessor $outbox,
    ) {}

    public function seed(): DemoSeedResult
    {
        [$userId, $workspaceId, $userCreated] = $this->ensureAccount(
            email: self::EMAIL,
            displayName: self::DISPLAY_NAME,
            password: self::PASSWORD,
            workspaceName: self::WORKSPACE_NAME,
            requestPrefix: 'demo-seed:v'.self::SCENARIO_VERSION,
        );

        $sampleDataSeeded = false;

        if (! $this->workspaceHasCurrentScenario($workspaceId)) {
            $this->seedSampleData($userId, $workspaceId);
            $this->drainOutbox();
            $sampleDataSeeded = true;
        }

        if ($this->seedScenarioContacts($userId, $workspaceId)) {
            $this->drainOutbox();
            $sampleDataSeeded = true;
        }

        if ($this->seedScenarioActivities($userId, $workspaceId)) {
            $this->drainOutbox();
            $sampleDataSeeded = true;
        }

        return new DemoSeedResult(
            email: self::EMAIL,
            password: self::PASSWORD,
            userId: $userId,
            workspaceId: $workspaceId,
            userCreated: $userCreated,
            sampleDataSeeded: $sampleDataSeeded,
            resourceCounts: $this->resourceCounts($workspaceId),
        );
    }

    public function seedEmpty(): DemoSeedResult
    {
        [$userId, $workspaceId, $userCreated] = $this->ensureAccount(
            email: self::EMPTY_EMAIL,
            displayName: self::EMPTY_DISPLAY_NAME,
            password: self::EMPTY_PASSWORD,
            workspaceName: self::EMPTY_WORKSPACE_NAME,
            requestPrefix: 'demo-empty:v'.self::EMPTY_SCENARIO_VERSION,
        );

        return new DemoSeedResult(
            email: self::EMPTY_EMAIL,
            password: self::EMPTY_PASSWORD,
            userId: $userId,
            workspaceId: $workspaceId,
            userCreated: $userCreated,
            sampleDataSeeded: false,
            resourceCounts: $this->resourceCounts($workspaceId),
        );
    }

    /** @return array{string, string, bool} */
    private function ensureAccount(
        string $email,
        string $displayName,
        string $password,
        string $workspaceName,
        string $requestPrefix,
    ): array {
        $userCreated = false;
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            $registered = $this->registerUser->handle(
                email: $email,
                displayName: $displayName,
                password: $password,
                requestId: $requestPrefix.':register',
            );
            $this->verifyEmail->handle($registered['user_id'], $registered['verification_token']);
            $userCreated = true;
            $userId = $registered['user_id'];
        } else {
            $userId = $user->id()->value;
        }

        $workspaceId = $this->resolveWorkspaceId($userId);

        if ($workspaceId === null) {
            $bootstrapped = $this->bootstrapWorkspace->handle(
                userId: $userId,
                workspaceName: $workspaceName,
                idempotencyKey: $requestPrefix.':bootstrap',
            );
            $workspaceId = $bootstrapped['workspace_id'];
        }

        return [$userId, $workspaceId, $userCreated];
    }

    private function seedSampleData(string $actorUserId, string $workspaceId): void
    {
        if (! $this->legacyBaselineExists($workspaceId)) {
            $this->seedDraftQuoteJourney($actorUserId, $workspaceId);
            $this->seedPaidInvoiceJourney($actorUserId, $workspaceId);
        }

        $this->seedSentQuoteJourney($actorUserId, $workspaceId);
        $this->seedAcceptedQuoteJourney($actorUserId, $workspaceId);
        $this->seedDraftInvoiceJourney($actorUserId, $workspaceId);
        $this->seedOverdueInvoiceJourney($actorUserId, $workspaceId);

        $this->drainOutbox(times: 4);

        $this->publishSnapshot->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            requestId: $this->requestId('analytics-snapshot'),
        );

        $this->drainOutbox(times: 4);
    }

    private function seedDraftQuoteJourney(string $actorUserId, string $workspaceId): void
    {
        $clientId = $this->seedClient(
            $actorUserId,
            $workspaceId,
            'Les Ateliers du Marais',
            'contact@ateliers-marais.test',
            'ateliers',
        );

        $this->createOpportunity->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $clientId,
            contactId: null,
            title: 'Audit express à qualifier',
            estimatedAmountCents: 80000,
            currency: 'EUR',
            requestId: $this->requestId('opp-audit'),
        );

        $this->seedQualifiedQuote(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $clientId,
            title: 'Refonte identité visuelle',
            amountCents: 350000,
            lines: [
                ['description' => 'Direction artistique', 'quantity' => 1, 'unit_price_cents' => 200000],
                ['description' => 'Déclinaisons supports', 'quantity' => 1, 'unit_price_cents' => 150000],
            ],
            suffix: 'refonte',
        );
    }

    private function seedPaidInvoiceJourney(string $actorUserId, string $workspaceId): void
    {
        $clientId = $this->seedClient(
            $actorUserId,
            $workspaceId,
            'Horizon Digital',
            'projets@horizon-digital.test',
            'horizon',
        );
        $quote = $this->seedQualifiedQuote(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $clientId,
            title: 'Migration cloud',
            amountCents: 120000,
            lines: [['description' => 'Migration infrastructure', 'quantity' => 1, 'unit_price_cents' => 120000]],
            suffix: 'migration',
        );
        $accepted = $this->sendAndAcceptQuote($workspaceId, $actorUserId, $quote, 'migration');
        $invoice = $this->createInvoiceFromQuote->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            quoteId: $accepted['quote_id'],
            requestId: $this->requestId('invoice-migration'),
        );
        $issued = $this->issueInvoice->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            invoiceId: $invoice['invoice_id'],
            expectedRevision: (int) $invoice['version'],
            requestId: $this->requestId('issue-migration'),
        );
        $this->sendInvoice->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            invoiceId: $invoice['invoice_id'],
            expectedRevision: (int) $issued['version'],
            requestId: $this->requestId('send-invoice-migration'),
        );
        $payment = $this->recordPayment->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            invoiceId: $invoice['invoice_id'],
            amountCents: 120000,
            reference: 'VIR-DEMO-HORIZON',
            requestId: $this->requestId('payment-migration'),
        );

        $this->backdatePaidInvoice($invoice['invoice_id'], $payment['payment_id']);
    }

    private function seedSentQuoteJourney(string $actorUserId, string $workspaceId): void
    {
        $clientId = $this->seedClient(
            $actorUserId,
            $workspaceId,
            'Maison Lumen',
            'bonjour@maison-lumen.test',
            'lumen',
        );
        $quote = $this->seedQualifiedQuote(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $clientId,
            title: 'Lancement e-commerce',
            amountCents: 480000,
            lines: [
                ['description' => 'Design UX/UI', 'quantity' => 1, 'unit_price_cents' => 300000],
                ['description' => 'Kit de lancement', 'quantity' => 1, 'unit_price_cents' => 180000],
            ],
            suffix: 'lumen',
        );

        $this->sendQuote->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            quoteId: $quote['quote_id'],
            expectedRevision: (int) $quote['version'],
            requestId: $this->requestId('send-lumen'),
        );
    }

    private function seedAcceptedQuoteJourney(string $actorUserId, string $workspaceId): void
    {
        $clientId = $this->seedClient(
            $actorUserId,
            $workspaceId,
            'Nova Conseil',
            'direction@nova-conseil.test',
            'nova',
        );
        $quote = $this->seedQualifiedQuote(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $clientId,
            title: 'Positionnement de marque',
            amountCents: 220000,
            lines: [['description' => 'Plateforme de marque', 'quantity' => 1, 'unit_price_cents' => 220000]],
            suffix: 'nova',
        );

        $this->sendAndAcceptQuote($workspaceId, $actorUserId, $quote, 'nova');
    }

    private function seedDraftInvoiceJourney(string $actorUserId, string $workspaceId): void
    {
        $clientId = $this->seedClient(
            $actorUserId,
            $workspaceId,
            'Cabinet Rivoli',
            'associes@cabinet-rivoli.test',
            'rivoli',
        );
        $quote = $this->seedQualifiedQuote(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $clientId,
            title: 'Nouveau site vitrine',
            amountCents: 180000,
            lines: [['description' => 'Conception du site', 'quantity' => 1, 'unit_price_cents' => 180000]],
            suffix: 'rivoli',
        );
        $accepted = $this->sendAndAcceptQuote($workspaceId, $actorUserId, $quote, 'rivoli');

        $this->createInvoiceFromQuote->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            quoteId: $accepted['quote_id'],
            requestId: $this->requestId('invoice-rivoli'),
        );
    }

    private function seedOverdueInvoiceJourney(string $actorUserId, string $workspaceId): void
    {
        $clientId = $this->seedClient(
            $actorUserId,
            $workspaceId,
            'Collectif Cobalt',
            'finance@collectif-cobalt.test',
            'cobalt',
        );
        $quote = $this->seedQualifiedQuote(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $clientId,
            title: 'Campagne annuelle',
            amountCents: 300000,
            lines: [
                ['description' => 'Concept créatif', 'quantity' => 1, 'unit_price_cents' => 180000],
                ['description' => 'Production digitale', 'quantity' => 1, 'unit_price_cents' => 120000],
            ],
            suffix: 'cobalt',
        );
        $accepted = $this->sendAndAcceptQuote($workspaceId, $actorUserId, $quote, 'cobalt');
        $invoice = $this->createInvoiceFromQuote->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            quoteId: $accepted['quote_id'],
            requestId: $this->requestId('invoice-cobalt'),
        );
        $issued = $this->issueInvoice->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            invoiceId: $invoice['invoice_id'],
            expectedRevision: (int) $invoice['version'],
            requestId: $this->requestId('issue-cobalt'),
        );
        $this->sendInvoice->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            invoiceId: $invoice['invoice_id'],
            expectedRevision: (int) $issued['version'],
            requestId: $this->requestId('send-invoice-cobalt'),
        );
        $payment = $this->recordPayment->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            invoiceId: $invoice['invoice_id'],
            amountCents: 60000,
            reference: 'ACOMPTE-DEMO-COBALT',
            requestId: $this->requestId('payment-cobalt'),
        );

        $this->backdateOverdueInvoice($invoice['invoice_id'], $payment['payment_id']);
    }

    private function seedClient(
        string $actorUserId,
        string $workspaceId,
        string $displayName,
        string $email,
        string $suffix,
    ): string {
        $client = $this->createClient->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            kind: 'Organization',
            displayName: $displayName,
            profile: [
                'email' => $email,
                'demo_scenario_version' => self::SCENARIO_VERSION,
            ],
            billingProfile: null,
            requestId: $this->requestId('client-'.$suffix),
        );

        return $client['client_id'];
    }

    private function seedScenarioContacts(string $actorUserId, string $workspaceId): bool
    {
        $contactCreated = false;

        foreach (self::CONTACTS_BY_CLIENT as $clientName => $profiles) {
            $client = DB::table('crm.clients')
                ->where('workspace_id', $workspaceId)
                ->where('display_name', $clientName)
                ->orderBy('created_at')
                ->first();

            if ($client === null) {
                continue;
            }

            $existingEmails = DB::table('crm.contacts')
                ->where('workspace_id', $workspaceId)
                ->where('client_id', $client->id)
                ->get()
                ->map(function ($row): ?string {
                    $profile = json_decode($row->profile, true, 512, JSON_THROW_ON_ERROR);

                    return is_string($profile['email'] ?? null) ? $profile['email'] : null;
                })
                ->filter()
                ->all();

            foreach ($profiles as $profile) {
                if (in_array($profile['email'], $existingEmails, true)) {
                    continue;
                }

                $makePrimary = $client->primary_contact_id === null;
                $contact = $this->addContact->handle(
                    actorUserId: $actorUserId,
                    workspaceId: $workspaceId,
                    clientId: $client->id,
                    profile: $profile,
                    makePrimary: $makePrimary,
                    expectedRevision: (int) $client->version,
                    requestId: $this->requestId('contact-'.str_replace(['@', '.'], '-', $profile['email'])),
                );

                $existingEmails[] = $profile['email'];
                $contactCreated = true;

                if ($makePrimary) {
                    $client->primary_contact_id = $contact['contact_id'];
                    $client->version = $contact['client_version'];
                }
            }
        }

        return $contactCreated;
    }

    private function seedScenarioActivities(string $actorUserId, string $workspaceId): bool
    {
        $activityCreated = false;

        foreach (self::ACTIVITIES as $index => $definition) {
            $client = DB::table('crm.clients')
                ->where('workspace_id', $workspaceId)
                ->where('display_name', $definition['client'])
                ->orderBy('created_at')
                ->first();

            if ($client === null) {
                continue;
            }

            $clientActivityIds = DB::table('crm.activities')
                ->select('id')
                ->where('workspace_id', $workspaceId)
                ->where('client_id', $client->id);
            $activityAlreadySeeded = (clone $clientActivityIds)
                ->where('summary', $definition['summary'])
                ->exists()
                || DB::table('crm.activity_revisions')
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('activity_id', $clientActivityIds)
                    ->where('summary', $definition['summary'])
                    ->exists();

            if ($activityAlreadySeeded) {
                continue;
            }

            $contactId = null;

            if ($definition['contact_email'] !== null) {
                $contactId = DB::table('crm.contacts')
                    ->where('workspace_id', $workspaceId)
                    ->where('client_id', $client->id)
                    ->get()
                    ->first(function ($contact) use ($definition): bool {
                        $profile = json_decode($contact->profile, true, 512, JSON_THROW_ON_ERROR);

                        return ($profile['email'] ?? null) === $definition['contact_email'];
                    })?->id;
            }

            $opportunityId = $definition['opportunity'] !== null
                ? DB::table('crm.opportunities')
                    ->where('workspace_id', $workspaceId)
                    ->where('client_id', $client->id)
                    ->where('title', $definition['opportunity'])
                    ->value('id')
                : null;

            $this->recordActivity->handle(
                actorUserId: $actorUserId,
                workspaceId: $workspaceId,
                clientId: $client->id,
                contactId: is_string($contactId) ? $contactId : null,
                opportunityId: is_string($opportunityId) ? $opportunityId : null,
                kind: $definition['kind'],
                summary: $definition['summary'],
                occurredAt: now()->subDays($definition['days_ago'])->toIso8601String(),
                requestId: $this->requestId('activity-'.($index + 1)),
            );
            $activityCreated = true;
        }

        return $activityCreated;
    }

    /**
     * @param  list<array{description: string, quantity: int, unit_price_cents: int}>  $lines
     * @return array<string, mixed>
     */
    private function seedQualifiedQuote(
        string $actorUserId,
        string $workspaceId,
        string $clientId,
        string $title,
        int $amountCents,
        array $lines,
        string $suffix,
    ): array {
        $opportunity = $this->createOpportunity->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $clientId,
            contactId: null,
            title: $title,
            estimatedAmountCents: $amountCents,
            currency: 'EUR',
            requestId: $this->requestId('opp-'.$suffix),
        );
        $this->qualifyOpportunity->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            opportunityId: $opportunity['opportunity_id'],
            expectedRevision: (int) $opportunity['version'],
            requestId: $this->requestId('qualify-'.$suffix),
        );

        return $this->createQuote->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $clientId,
            opportunityId: $opportunity['opportunity_id'],
            lines: $lines,
            currency: 'EUR',
            requestId: $this->requestId('quote-'.$suffix),
        );
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function sendAndAcceptQuote(
        string $workspaceId,
        string $actorUserId,
        array $quote,
        string $suffix,
    ): array {
        $sent = $this->sendQuote->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            quoteId: $quote['quote_id'],
            expectedRevision: (int) $quote['version'],
            requestId: $this->requestId('send-'.$suffix),
        );

        return $this->acceptQuote->handle(
            workspaceId: $workspaceId,
            quoteId: $quote['quote_id'],
            publicToken: $sent['public_accept_token'],
            expectedRevision: (int) $sent['version'],
            requestId: $this->requestId('accept-'.$suffix),
        );
    }

    private function backdatePaidInvoice(string $invoiceId, string $paymentId): void
    {
        DB::table('billing.invoices')->where('id', $invoiceId)->update([
            'issued_at' => now()->subDays(20),
            'sent_at' => now()->subDays(19),
            'due_date' => now()->addDays(10),
            'paid_at' => now()->subDays(5),
        ]);
        DB::table('billing.payments')->where('id', $paymentId)->update([
            'recorded_at' => now()->subDays(5),
        ]);
    }

    private function backdateOverdueInvoice(string $invoiceId, string $paymentId): void
    {
        DB::table('billing.invoices')->where('id', $invoiceId)->update([
            'issued_at' => now()->subDays(45),
            'sent_at' => now()->subDays(44),
            'due_date' => now()->subDays(15),
        ]);
        DB::table('billing.payments')->where('id', $paymentId)->update([
            'recorded_at' => now()->subDays(10),
        ]);
    }

    private function drainOutbox(int $times = 3): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->outbox->processPending();
        }
    }

    private function workspaceHasCurrentScenario(string $workspaceId): bool
    {
        $expectedClients = DB::table('crm.clients')
            ->where('workspace_id', $workspaceId)
            ->whereIn('display_name', self::EXPECTED_CLIENT_NAMES)
            ->count();

        return $expectedClients === count(self::EXPECTED_CLIENT_NAMES)
            && DB::table('billing.quotes')->where('workspace_id', $workspaceId)->count() >= 6
            && DB::table('billing.invoices')->where('workspace_id', $workspaceId)->count() >= 3
            && DB::table('billing.payments')->where('workspace_id', $workspaceId)->count() >= 2
            && DB::table('analytics.snapshots')->where('workspace_id', $workspaceId)->exists()
            && DB::table('business_health.current_assessments')->where('workspace_id', $workspaceId)->exists()
            && DB::table('advisor.overviews')->where('workspace_id', $workspaceId)->exists();
    }

    private function legacyBaselineExists(string $workspaceId): bool
    {
        return DB::table('crm.clients')
            ->where('workspace_id', $workspaceId)
            ->whereIn('display_name', ['Les Ateliers du Marais', 'Horizon Digital'])
            ->count() === 2;
    }

    /** @return array<string, int> */
    private function resourceCounts(string $workspaceId): array
    {
        return [
            'clients' => DB::table('crm.clients')->where('workspace_id', $workspaceId)->count(),
            'contacts' => DB::table('crm.contacts')->where('workspace_id', $workspaceId)->count(),
            'opportunities' => DB::table('crm.opportunities')->where('workspace_id', $workspaceId)->count(),
            'activities' => DB::table('crm.activities')->where('workspace_id', $workspaceId)->count(),
            'quotes' => DB::table('billing.quotes')->where('workspace_id', $workspaceId)->count(),
            'invoices' => DB::table('billing.invoices')->where('workspace_id', $workspaceId)->count(),
            'active_recommendations' => DB::table('advisor.recommendations')
                ->where('workspace_id', $workspaceId)
                ->where('status', 'Generated')
                ->count(),
            'unread_notifications' => DB::table('notifications.notifications')
                ->where('workspace_id', $workspaceId)
                ->where('status', 'Active')
                ->where('read_state', 'Unread')
                ->count(),
        ];
    }

    private function resolveWorkspaceId(string $userId): ?string
    {
        $workspaceId = DB::table('identity.memberships')
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->value('workspace_id');

        return is_string($workspaceId) ? $workspaceId : null;
    }

    private function requestId(string $suffix): string
    {
        return 'demo-seed:v'.self::SCENARIO_VERSION.':'.$suffix;
    }
}
