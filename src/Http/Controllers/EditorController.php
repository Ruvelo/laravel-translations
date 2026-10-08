<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Ruvelo\Translations\Entry;
use Ruvelo\Translations\Http\Concerns\ResolvesKeys;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Translations;

class EditorController
{
    use ResolvesKeys;

    public const FILTERS = ['all', 'missing', 'changed', 'warnings'];

    public function __invoke(Request $request, string $locale): Response
    {
        $this->assertLocale($locale);

        $filter = in_array($request->query('filter'), self::FILTERS, true) ? (string) $request->query('filter') : 'all';
        $search = is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 200) : '';
        $file = is_string($request->query('file')) && $request->query('file') !== '' ? $request->query('file') : null;

        $catalogue = Translations::catalogue();
        $files = collect($catalogue->groups())->map(fn (array $pair) => Key::fileId($pair[0], $pair[1]))->all();
        if ($file !== null && ! in_array($file, $files, true)) {
            $file = null;
        }

        $all = $catalogue->filter($locale, 'all', $search, $file);
        $counts = [
            'all' => $all->count(),
            'missing' => $all->filter(fn (Entry $entry) => $entry->isMissing())->count(),
            'changed' => $all->filter(fn (Entry $entry) => $entry->isPending())->count(),
            'warnings' => $all->filter(fn (Entry $entry) => $entry->warnings() !== [])->count(),
        ];

        $entries = $catalogue->filter($locale, $filter, $search, $file);
        $perPage = max(1, (int) config('translations.per_page', 50));
        $page = max(1, (int) $request->query('page', '1'));

        $paginator = new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values(),
            $entries->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => array_filter(['filter' => $filter === 'all' ? null : $filter, 'q' => $search, 'file' => $file])],
        );

        return response()->view('translations::editor', [
            'locale' => $locale,
            'localeName' => Translations::localeName($locale),
            'source' => Translations::sourceLocale(),
            'sourceName' => Translations::localeName(Translations::sourceLocale()),
            'locales' => Translations::locales(),
            'entries' => $paginator,
            'filter' => $filter,
            'search' => $search,
            'file' => $file,
            'files' => $files,
            'counts' => $counts,
            'progress' => Translations::progress($locale),
            'canSuggest' => $locale !== Translations::sourceLocale() && Translations::canSuggest(),
        ]);
    }
}
