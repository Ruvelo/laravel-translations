<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Loader;

use Illuminate\Translation\Translator;
use Ruvelo\Translations\Support\KeyRecorder;

/**
 * Laravel's translator, remembering which keys the page asked for. That is
 * all it changes: every lookup still goes through the parent.
 */
class RecordingTranslator extends Translator
{
    private ?KeyRecorder $recorder = null;

    public function setRecorder(?KeyRecorder $recorder): void
    {
        $this->recorder = $recorder;
    }

    /**
     * @param  string  $key
     * @param  array<string, mixed>  $replace
     * @param  string|null  $locale
     * @param  bool  $fallback
     * @return string|array<array-key, mixed>
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        if ($this->recorder !== null && is_string($key)) {
            $recorder = $this->recorder;
            $recorder->record($key);

            // The lookup itself may call get() again (e.g. fallbacks); those
            // are not separate strings on the page.
            return $recorder->paused(fn () => parent::get($key, $replace, $locale, $fallback));
        }

        return parent::get($key, $replace, $locale, $fallback);
    }

    /**
     * has() is a question, not a string shown on the page.
     *
     * @param  string  $key
     * @param  string|null  $locale
     * @param  bool  $fallback
     * @return bool
     */
    public function has($key, $locale = null, $fallback = true)
    {
        if ($this->recorder === null) {
            return parent::has($key, $locale, $fallback);
        }

        return $this->recorder->paused(fn () => parent::has($key, $locale, $fallback));
    }
}
