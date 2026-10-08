<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Translations;

class ScanCommand extends Command
{
    protected $signature = 'translations:scan
        {paths?* : Folders or files to read (default: translations.scan.paths)}
        {--js : Also read .js, .ts, .vue, .jsx and .tsx files}
        {--create : Add the missing keys to the source locale, as pending changes}
        {--unused : List keys nothing seems to use}';

    protected $description = 'Find translation keys your code uses that the source locale is missing';

    public function handle(): int
    {
        /** @var list<string> $paths */
        $paths = array_values(array_filter((array) $this->argument('paths'), 'is_string'));
        $result = Translations::scan($paths === [] ? null : $paths, $this->option('js') ? true : null);
        $source = Translations::sourceLocale();

        $this->components->info(sprintf('Read %d files and found %d keys.', $result->files, count($result->used)));

        if ($result->missing === []) {
            $this->components->info("Every key is in the source locale ({$source}).");
        } else {
            $this->components->warn(count($result->missing)." keys are missing from {$source}:");
            foreach ($result->missing as $key) {
                $this->components->twoColumnDetail($key->full(), $result->locations($key)[0] ?? '');
            }

            if ($this->option('create')) {
                foreach ($result->missing as $key) {
                    Translations::set($source, $key, $this->placeholderText($key));
                }
                $this->components->info('Added them to '.$source.' as pending changes. Check the wording in the editor, then export.');
            } else {
                $this->line('  Run again with <options=bold>--create</> to add them.');
            }
        }

        if ($this->option('unused')) {
            $this->newLine();
            if ($result->unused === []) {
                $this->components->info('Every key in the source locale is used.');
            } else {
                $this->components->warn(count($result->unused).' keys look unused. Keys built at runtime (__("status.".$name)) can hide here, so check before deleting:');
                foreach ($result->unused as $key) {
                    $this->line('  '.$key->full());
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * JSON keys are their own text; for file keys, the last part made
     * readable ("due_date" becomes "Due date").
     */
    private function placeholderText(Key $key): string
    {
        if ($key->isJson()) {
            return $key->item;
        }

        $last = Str::afterLast($key->item, '.');

        return Str::ucfirst(str_replace(['_', '-'], ' ', Str::snake($last)));
    }
}
