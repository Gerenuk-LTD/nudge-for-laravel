<?php

namespace Gerenuk\Nudge\Endpoints;

use Gerenuk\Nudge\Data\ContactList;
use Gerenuk\Nudge\Data\CursorPage;
use Gerenuk\Nudge\Data\ListMembershipChange;
use Gerenuk\Nudge\Http\ApiRequest;
use Gerenuk\Nudge\Support\Attributes;
use Illuminate\Support\LazyCollection;

final readonly class Lists extends Endpoint
{
    /** Nudge accepts up to this many emails per request. Longer lists are sent in batches. */
    public const int BATCH_SIZE = 1000;

    /**
     * One page of lists, by name.
     *
     * @return CursorPage<ContactList>
     */
    public function list(int $perPage = 100, ?string $cursor = null): CursorPage
    {
        return $this->page($this->nudge->send(ApiRequest::get('lists', ['per_page' => $perPage, 'cursor' => $cursor])), ContactList::fromArray(...));
    }

    /**
     * Every list, by name, fetched a page at a time as you iterate.
     *
     * @return LazyCollection<int, ContactList>
     */
    public function all(int $perPage = 500): LazyCollection
    {
        return $this->everyPage(fn (?string $cursor): CursorPage => $this->list($perPage, $cursor));
    }

    public function find(int $id): ?ContactList
    {
        $response = $this->sendOrNull(ApiRequest::get('lists/' . $id));

        return $response === null ? null : ContactList::fromArray($response->data());
    }

    public function create(string $name, ?string $description = null): ContactList
    {
        return ContactList::fromArray($this->nudge->send(ApiRequest::post('lists', array_filter([
            'name' => $name,
            'description' => $description,
        ], fn (?string $value): bool => $value !== null)))->data());
    }

    /**
     * Change a list's name or description. Pass an empty description to clear it.
     */
    public function update(int $id, ?string $name = null, ?string $description = null): ContactList
    {
        $body = array_filter(['name' => $name], fn (?string $value): bool => $value !== null);

        if ($description !== null) {
            $body['description'] = $description === '' ? null : $description;
        }

        return ContactList::fromArray($this->nudge->send(ApiRequest::put('lists/' . $id, $body))->data());
    }

    /**
     * Delete a list. Its contacts are kept.
     */
    public function delete(int $id): void
    {
        $this->nudge->send(ApiRequest::delete('lists/' . $id));
    }

    /**
     * Add existing contacts to a list by email. Emails with no contact are reported back, not created.
     *
     * @param  iterable<string>  $emails
     */
    public function addContacts(int $id, iterable $emails): ListMembershipChange
    {
        return $this->changeMembership('POST', $id, $emails, 'added');
    }

    /**
     * @param  iterable<string>  $emails
     */
    public function removeContacts(int $id, iterable $emails): ListMembershipChange
    {
        return $this->changeMembership('DELETE', $id, $emails, 'removed');
    }

    /**
     * @param  iterable<string>  $emails
     */
    private function changeMembership(string $method, int $id, iterable $emails, string $countKey): ListMembershipChange
    {
        $count = 0;
        $notFound = [];

        foreach (collect($emails)->unique()->values()->chunk(self::BATCH_SIZE) as $batch) {
            $data = new Attributes($this->nudge->send(new ApiRequest($method, "lists/{$id}/contacts", body: ['emails' => $batch->values()->all()]))->data());

            $count += $data->int($countKey);
            $notFound = [...$notFound, ...$data->strings('not_found')];
        }

        return new ListMembershipChange($count, $notFound);
    }
}
