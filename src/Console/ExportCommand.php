<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Console;

use Illuminate\Console\Command;
use Ruvelo\Translations\Exceptions\TranslationsException;
use Ruvelo\Translations\Translations;

class ExportCommand extends Command
{
    protected $signature = 'translations:export
        {--locale=* : Only these locales}
        {--dry-run : Show the changes as a diff without writing anything}
        {--prune : Delete the overrides once the lang files hold them}';

    protected $description = 'Write the translations edited in the browser into your lang files';

    public function handle(): int
    {
        /** @var list<string> $locales */
        $locales = array_values(array_filter((array) $this->option('locale'), 'is_string'));
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = Translations::export($locales === [] ? null : $locales, $dryRun, (bool) $this->option('prune'));
        } catch (TranslationsException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result->isEmpty()) {
            $this->components->info('Nothing to export: the lang files already say what the app shows.');

            return self::SUCCESS;
        }

        foreach ($result->files as $file) {
            if ($dryRun) {
                $this->line($this->colour($file->diff()));
                $this->newLine();
            } else {
                $this->components->twoColumnDetail(
                    $file->path.($file->rewritten ? ' <fg=yellow>(rewritten: formatting not kept)</>' : ''),
                    ($file->isNew() ? '<fg=green>new</>, ' : '').$file->changes.' '.($file->changes === 1 ? 'change' : 'changes'),
                );
            }
        }

        $count = $result->count().' '.($result->count() === 1 ? 'change' : 'changes');
        $files = count($result->files).' '.(count($result->files) === 1 ? 'file' : 'files');

        if ($dryRun) {
            $this->components->info("Would write {$count} to {$files}. Run without --dry-run to write them.");
        } else {
            $this->components->info("Wrote {$count} to {$files}. Review them with git diff, then commit.");
            if ($result->pruned > 0) {
                $this->components->info("Deleted {$result->pruned} overrides now in the files.");
            }
        }

        return self::SUCCESS;
    }

    private function colour(string $diff): string
    {
        return implode("\n", array_map(function (string $line) {
            $escaped = str_replace('<', '\\<', $line);

            return match (true) {
                str_starts_with($line, '+++'), str_starts_with($line, '---') => "<options=bold>{$escaped}</>",
                str_starts_with($line, '+') => "<fg=green>{$escaped}</>",
                str_starts_with($line, '-') => "<fg=red>{$escaped}</>",
                str_starts_with($line, '@@') => "<fg=cyan>{$escaped}</>",
                default => $escaped,
            };
        }, explode("\n", $diff)));
    }
}
