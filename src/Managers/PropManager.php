<?php

namespace Lunar\Storefront\Managers;

use Illuminate\Support\Collection;
use Lunar\Storefront\PropData;
use Lunar\Storefront\StorefrontPage;

class PropManager implements \Lunar\Storefront\Contracts\PropManager
{
    protected array $props = [];

    public function resolve(StorefrontPage|string $page, mixed $record = null): array
    {
        $pageValue = $page instanceof StorefrontPage ? $page->value : $page;

        $props = collect($this->props)->filter(
            fn ($prop) => $prop->getPage() === $pageValue
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
        if (! is_iterable($propData)) {
            $propData = [$propData];
        }

        $this->props = [
            ...$this->props,
            ...$propData,
        ];
    }
}
