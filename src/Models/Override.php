<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Ruvelo\Translations\Database\Factories\OverrideFactory;
use Ruvelo\Translations\Events\TranslationUpdated;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Support\OverrideCache;

/**
 * A translation edited in the browser, applied on top of the lang files
 * until it is exported into them.
 *
 * @property int $id
 * @property string $locale
 * @property string $namespace
 * @property string $group
 * @property string $key
 * @property string $hash
 * @property string $value
 * @property string|null $file_value
 * @property string|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Override extends Model
{
    /** @use HasFactory<OverrideFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Every way a row can change clears that file's compiled overrides.
        static::saved(fn (Override $override) => OverrideCache::forget($override->locale, $override->namespace, $override->group));
        static::deleted(fn (Override $override) => OverrideCache::forget($override->locale, $override->namespace, $override->group));
    }

    protected static function newFactory(): OverrideFactory
    {
        return OverrideFactory::new();
    }

    public function getTable(): string
    {
        return config('translations.table_prefix', 'translations_').'overrides';
    }

    /**
     * The one write path for overrides: create or update the translation of
     * `$key` in `$locale`, and announce it.
     *
     * @param  string|null  $fileValue  What the lang file says right now
     */
    public static function put(string $locale, Key $key, string $value, ?string $fileValue, ?Authenticatable $by = null): self
    {
        $override = static::query()->where('locale', $locale)->where('hash', $key->hash())->first()
            ?? new self(['locale' => $locale, ...$key->toArray(), 'hash' => $key->hash()]);

        $previous = $override->exists ? $override->value : $fileValue;

        if ($override->exists && $override->value === $value) {
            return $override;
        }

        $override->value = $value;
        $override->file_value = $fileValue;
        $byId = $by?->getAuthIdentifier();
        $override->updated_by = is_scalar($byId) ? (string) $byId : null;
        $override->save();

        event(new TranslationUpdated($locale, $key, $previous, $value, $by));

        return $override;
    }

    /**
     * Remove the override, so the lang file's text applies again.
     */
    public static function revert(string $locale, Key $key, ?Authenticatable $by = null, ?string $fileValue = null): bool
    {
        $override = static::query()->where('locale', $locale)->where('hash', $key->hash())->first();

        if ($override === null) {
            return false;
        }

        $override->delete();

        event(new TranslationUpdated($locale, $key, $override->value, $fileValue, $by, reverted: true));

        return true;
    }

    public function translationKey(): Key
    {
        return Key::fromParts($this->namespace, $this->group, $this->key);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForFile(Builder $query, string $locale, string $namespace, string $group): Builder
    {
        return $query->where('locale', $locale)->where('namespace', $namespace)->where('group', $group);
    }

    /**
     * The editor's display name, if the user model still has them.
     */
    public function editorName(): ?string
    {
        if ($this->updated_by === null) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = config('translations.user_model') ?? config('auth.providers.users.model') ?? 'App\\Models\\User';

        if (! class_exists($model)) {
            return null;
        }

        $user = $model::query()->find($this->updated_by);
        $name = $user?->getAttribute((string) config('translations.user_name_attribute', 'name'));

        return is_string($name) && $name !== '' ? $name : null;
    }
}
