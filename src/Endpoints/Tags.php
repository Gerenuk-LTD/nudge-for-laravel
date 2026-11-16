<?php

namespace Gerenuk\Nudge\Endpoints;

use Gerenuk\Nudge\Data\CursorPage;
use Gerenuk\Nudge\Data\Tag;
use Gerenuk\Nudge\Http\ApiRequest;
use Illuminate\Support\LazyCollection;

/**
 * Tags are created by giving them to a contact, so they can only be listed here.
 */
final readonly class Tags extends Endpoint
{
    /**
     * One page of tags, by name.
     *
     * @return CursorPage<Tag>
     */
    public function list(int $perPage = 100, ?string $cursor = null): CursorPage
    {
        return $this->page($this->nudge->send(ApiRequest::get('tags', ['per_page' => $perPage, 'cursor' => $cursor])), Tag::fromArray(...));
    }

    /**
     * Every tag, by name, fetched a page at a time as you iterate.
     *
     * @return LazyCollection<int, Tag>
     */
    public function all(int $perPage = 500): LazyCollection
    {
        return $this->everyPage(fn (?string $cursor): CursorPage => $this->list($perPage, $cursor));
    }
}
