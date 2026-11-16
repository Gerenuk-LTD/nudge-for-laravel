<?php

namespace Gerenuk\Nudge\Endpoints;

use Gerenuk\Nudge\Data\Activity;
use Gerenuk\Nudge\Data\Contact;
use Gerenuk\Nudge\Data\CursorPage;
use Gerenuk\Nudge\Enums\ContactStatus;
use Gerenuk\Nudge\Http\ApiRequest;
use Illuminate\Support\LazyCollection;

/**
 * Reading and archiving contacts. To create or update one, use `Nudge::contact($email)`.
 */
final readonly class Contacts extends Endpoint
{
    /**
     * One page of contacts, filtered. `list`, `tag` and `segment` are IDs.
     *
     * @return CursorPage<Contact>
     */
    public function list(
        ?ContactStatus $status = null,
        ?int $list = null,
        ?int $tag = null,
        ?int $segment = null,
        ?bool $archived = null,
        ?string $search = null,
        int $perPage = 100,
        ?string $cursor = null,
    ): CursorPage {
        return $this->page($this->nudge->send(ApiRequest::get('contacts', [
            'status' => $status?->value,
            'list' => $list,
            'tag' => $tag,
            'segment' => $segment,
            'archived' => $archived === null ? null : (int) $archived,
            'search' => $search,
            'per_page' => $perPage,
            'cursor' => $cursor,
        ])), Contact::fromArray(...));
    }

    /**
     * Every matching contact, fetched a page at a time as you iterate.
     *
     * @return LazyCollection<int, Contact>
     */
    public function all(
        ?ContactStatus $status = null,
        ?int $list = null,
        ?int $tag = null,
        ?int $segment = null,
        ?bool $archived = null,
        ?string $search = null,
        int $perPage = 500,
    ): LazyCollection {
        return $this->everyPage(fn (?string $cursor): CursorPage => $this->list($status, $list, $tag, $segment, $archived, $search, $perPage, $cursor));
    }

    public function find(string $email): ?Contact
    {
        $response = $this->sendOrNull(ApiRequest::get('contacts/' . ApiRequest::segment($email)));

        return $response === null ? null : Contact::fromArray($response->data());
    }

    /**
     * Archive a contact, so they're no longer emailed. Their history is kept.
     */
    public function archive(string $email): void
    {
        $this->nudge->send(ApiRequest::delete('contacts/' . ApiRequest::segment($email)));
    }

    /**
     * One page of a contact's timeline, newest first.
     *
     * @return CursorPage<Activity>
     */
    public function activity(string $email, int $perPage = 100, ?string $cursor = null): CursorPage
    {
        return $this->page(
            $this->nudge->send(ApiRequest::get('contacts/' . ApiRequest::segment($email) . '/events', ['per_page' => $perPage, 'cursor' => $cursor])),
            Activity::fromArray(...),
        );
    }

    /**
     * A contact's whole timeline, newest first, fetched a page at a time as you iterate.
     *
     * @return LazyCollection<int, Activity>
     */
    public function allActivity(string $email, int $perPage = 500): LazyCollection
    {
        return $this->everyPage(fn (?string $cursor): CursorPage => $this->activity($email, $perPage, $cursor));
    }
}
