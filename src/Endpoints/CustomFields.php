<?php

namespace Gerenuk\Nudge\Endpoints;

use Gerenuk\Nudge\Data\CursorPage;
use Gerenuk\Nudge\Data\CustomField;
use Gerenuk\Nudge\Enums\CustomFieldType;
use Gerenuk\Nudge\Http\ApiRequest;
use Illuminate\Support\LazyCollection;

final readonly class CustomFields extends Endpoint
{
    /**
     * One page of custom fields, by key.
     *
     * @return CursorPage<CustomField>
     */
    public function list(int $perPage = 100, ?string $cursor = null): CursorPage
    {
        return $this->page($this->nudge->send(ApiRequest::get('custom-fields', ['per_page' => $perPage, 'cursor' => $cursor])), CustomField::fromArray(...));
    }

    /**
     * Every custom field, by key, fetched a page at a time as you iterate.
     *
     * @return LazyCollection<int, CustomField>
     */
    public function all(int $perPage = 500): LazyCollection
    {
        return $this->everyPage(fn (?string $cursor): CursorPage => $this->list($perPage, $cursor));
    }

    /**
     * Define a new field. Keys are lowercase letters, numbers and "_", starting with a letter.
     */
    public function create(string $key, string $label, CustomFieldType $type = CustomFieldType::Text): CustomField
    {
        return CustomField::fromArray($this->nudge->send(ApiRequest::post('custom-fields', [
            'key' => $key,
            'label' => $label,
            'type' => $type->value,
        ]))->data());
    }
}
