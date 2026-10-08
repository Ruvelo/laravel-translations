<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Ruvelo\Translations\Exceptions\TranslationsException;
use Ruvelo\Translations\Import\Importer;

class ImportCommand extends Command
{
    protected $signature = 'translations:import
        {path? : A .json or .php file, or a folder laid out like lang/}
        {--locale= : The locale of a single file (guessed from fr.json or fr/billing.php)}
        {--group= : For a .php file, the file it belongs to (default: its name)}
        {--translation-manager : Import from barryvdh/laravel-translation-manager\'s ltm_translations table}
        {--table=ltm_translations : The translation manager\'s table}
        {--user= : The id of the user to attribute the changes to}';

    protected $description = 'Bring translations in as pending changes to review and export';

    public function handle(Importer $importer): int
    {
        $path = $this->argument('path');
        $user = $this->user();

        try {
            if ($this->option('translation-manager')) {
                $result = $importer->fromTranslationManager(is_string($table = $this->option('table')) ? $table : 'ltm_translations', by: $user);
            } elseif (is_string($path) && $path !== '') {
                $locale = $this->option('locale');
                $group = $this->option('group');
                $result = $importer->fromPath($path, is_string($locale) ? $locale : null, is_string($group) ? $group : null, by: $user);
            } else {
                $this->components->error('Give a file or folder to import, or --translation-manager.');

                return self::FAILURE;
            }
        } catch (TranslationsException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach (array_unique($result->skipped) as $reason) {
            $this->components->warn('Skipped: '.$reason);
        }

        $this->components->info(sprintf(
            'Imported %d %s (%d already matched). Review them under “Pending changes”, then run translations:export.',
            $result->count(),
            $result->count() === 1 ? 'translation' : 'translations',
            $result->unchanged,
        ));

        return self::SUCCESS;
    }

    private function user(): ?Authenticatable
    {
        $id = $this->option('user');
        /** @var class-string<Model> $model */
        $model = config('translations.user_model') ?? config('auth.providers.users.model') ?? 'App\\Models\\User';

        if (! is_string($id) || $id === '' || ! class_exists($model)) {
            return null;
        }

        $user = $model::query()->find($id);

        return $user instanceof Authenticatable ? $user : null;
    }
}
