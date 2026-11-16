<?php

namespace Gerenuk\Nudge\Endpoints;

use Gerenuk\Nudge\Data\Contact;
use Gerenuk\Nudge\Data\CursorPage;
use Gerenuk\Nudge\Data\Segment;
use Gerenuk\Nudge\Http\ApiRequest;
use Illuminate\Support\LazyCollection;

/**
 * Segments are built in Nudge. Here they can be read, along with the contacts that match them.
 */
final readonly class Segments extends Endpoint
{
    /**
     * One page of segments, by name, with their definitions. Contact counts come only from `find()`.
     *
     * @return CursorPage<Segment>
     */
    public function list(int $perPage = 100, ?string $cursor = null): CursorPage
    {
        return $this->page($this->nudge->send(ApiRequest::get('segments', ['per_page' => $perPage, 'cursor' => $cursor])), Segment::fromArray(...));
    }

    /**
     * Every segment, by name, fetched a page at a time as you iterate.
     *
     * @return LazyCollection<int, Segment>
     */
    public function all(int $perPage = 500): LazyCollection
    {
        return $this->everyPage(fn (?string $cursor): CursorPage => $this->list($perPage, $cursor));
    }

    /**
     * A segment with its definition and how many contacts match it now.
     */
    public function find(int $id): ?Segment
    {
        $response = $this->sendOrNull(ApiRequest::get('segments/' . $id));

        return $response === null ? null : Segment::fromArray($response->data());
    }

    /**
     * @return CursorPage<Contact>
     */
    public function contacts(int $id, int $perPage = 100, ?string $cursor = null): CursorPage
    {
        return $this->page(
            $this->nudge->send(ApiRequest::get("segments/{$id}/contacts", ['per_page' => $perPage, 'cursor' => $cursor])),
            Contact::fromArray(...),
        );
    }

    /**
     * @return LazyCollection<int, Contact>
     */
    public function allContacts(int $id, int $perPage = 500): LazyCollection
    {
        return $this->everyPage(fn (?string $cursor): CursorPage => $this->contacts($id, $perPage, $cursor));
    }
}
