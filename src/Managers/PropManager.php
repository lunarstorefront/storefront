<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lunar\Storefront\PropData;

class PropManager implements \Lunar\Storefront\Contracts\PropManager
{
    protected array $props = [];

    public function resolve(string $page, ?Model $record = null): array
    {
        $props = collect($this->props)->filter(
            fn ($prop) => $prop->getPage() === $page
        );

        $data = [];

        foreach ($props as $prop) {
            if (is_string($prop->callback) && class_exists($prop->callback)) {
                $data[$prop->key] = (new $prop->callback)($record);
                continue;
            }

            $data[$prop->key] = call_user_func($prop->callback, $record);
        }

        return $data;
    }

    public function add(array|Collection|PropData $propData): void
    {
        if (!is_iterable($propData)) {
            $propData = [$propData];
        }

        $this->props = [
            ...$this->props,
            ...$propData
        ];
    }
}
