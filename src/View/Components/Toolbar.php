<?php

declare(strict_types=1);

namespace Ruvelo\Translations\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Ruvelo\Translations\Entry;
use Ruvelo\Translations\Exceptions\InvalidKey;
use Ruvelo\Translations\Http\Controllers\EditModeController;
use Ruvelo\Translations\Support\KeyRecorder;
use Ruvelo\Translations\Translations;

/**
 * <x-translations::toolbar />: the "Edit translations" button and, in edit
 * mode, the panel listing the strings this page used. Renders nothing for
 * anyone who can't edit translations.
 *
 * Place it last in your layout's <body>, so the page has been rendered
 * (and its keys recorded) by the time it runs.
 */
class Toolbar extends Component
{
    public const MARKER = '<!--translations-toolbar-->';

    public function shouldRender(): bool
    {
        return (bool) config('translations.in_context.enabled', true) && Translations::canEdit();
    }

    public function render(): View
    {
        return app(KeyRecorder::class)->paused(fn () => $this->view('translations::components.toolbar', $this->data()));
    }

    /**
     * The rendered HTML, or '' for people who can't edit.
     */
    public static function html(): string
    {
        $toolbar = new self;

        return $toolbar->shouldRender() ? $toolbar->render()->render() : '';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $request = request();
        $editMode = $request->hasSession() && (bool) $request->session()->get(EditModeController::SESSION_KEY, false);
        $locale = app()->getLocale();
        $catalogue = Translations::catalogue();
        $entries = [];

        if ($editMode && $catalogue->hasLocale($locale)) {
            foreach (Translations::recordedKeys() as $string) {
                try {
                    $entry = $catalogue->entry($locale, $catalogue->resolve($string, $locale));
                } catch (InvalidKey) {
                    continue;
                }

                // A whole file or section (__('billing.invoice')) isn't one string.
                if (is_array(app('translator')->get($string, [], $locale))) {
                    continue;
                }

                $entries[$entry->key->hash()] = $entry;
            }
        }

        return [
            'marker' => self::MARKER,
            'editMode' => $editMode,
            'locale' => $locale,
            'localeName' => $catalogue->localeName($locale),
            'knownLocale' => $catalogue->hasLocale($locale),
            'isSource' => $locale === $catalogue->sourceLocale(),
            'sourceName' => $catalogue->localeName($catalogue->sourceLocale()),
            'entries' => array_values($entries),
            'missing' => count(array_filter($entries, fn (Entry $entry) => $entry->isMissing())),
        ];
    }
}
