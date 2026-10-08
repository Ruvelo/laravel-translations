<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Console;

use Illuminate\Console\Command;
use Ruvelo\Translations\Translations;

class PruneCommand extends Command
{
    protected $signature = 'translations:prune';

    protected $description = 'Delete overrides the lang files now hold (run it after deploying an export)';

    public function handle(): int
    {
        $count = Translations::prune();

        $this->components->info($count === 0
            ? 'Nothing to prune.'
            : "Deleted {$count} ".($count === 1 ? 'override' : 'overrides').' the lang files now hold.');

        return self::SUCCESS;
    }
}
