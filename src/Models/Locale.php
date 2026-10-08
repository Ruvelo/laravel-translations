<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A locale added in the browser before it has any lang files.
 *
 * @property int $id
 * @property string $code
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Locale extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('translations.table_prefix', 'translations_').'locales';
    }
}
