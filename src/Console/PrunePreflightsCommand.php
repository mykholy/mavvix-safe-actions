<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Mavvix\SafeActions\Preflight\SafeActionPreflight;

final class PrunePreflightsCommand extends Command
{
    protected $signature = 'safe-actions:prune-preflights
                            {--hours=24 : Retain expired and consumed tickets for this many hours}';

    protected $description = 'Delete old expired or consumed Safe Actions preflight tickets';

    public function handle(): int
    {
        $hours = filter_var(
            $this->option('hours'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 8760]],
        );

        if ($hours === false) {
            $this->components->error('The --hours option must be an integer from 1 to 8760.');

            return self::FAILURE;
        }

        $cutoff = now()->subHours($hours);

        $deleted = SafeActionPreflight::query()
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where('expires_at', '<', $cutoff)
                    ->orWhere('consumed_at', '<', $cutoff);
            })
            ->delete();

        $this->components->info("Pruned {$deleted} old Safe Actions preflight tickets.");

        return self::SUCCESS;
    }
}
