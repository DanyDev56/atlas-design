<?php

declare(strict_types=1);

namespace Atlas\Platform\Messaging\Spike;

use Atlas\Platform\Messaging\OutboxConsumer;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;

final class SpikeEventCounterConsumer implements OutboxConsumer
{
    public function name(): string
    {
        return 'spike.event_counter';
    }

    public function handle(OutgoingMessage $message): void
    {
        $existing = DB::table('platform.spike_consumer_effects')
            ->where('consumer_name', $this->name())
            ->first();

        if ($existing === null) {
            DB::table('platform.spike_consumer_effects')->insert([
                'consumer_name' => $this->name(),
                'effect_count' => 1,
            ]);

            return;
        }

        DB::table('platform.spike_consumer_effects')
            ->where('consumer_name', $this->name())
            ->update(['effect_count' => ((int) $existing->effect_count) + 1]);
    }
}
