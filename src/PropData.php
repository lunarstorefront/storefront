<?php

namespace Lunar\Storefront;

class PropData
{
    public function __construct(
        public StorefrontPage|string $page,
        public string $key,
        public \Closure|string $callback,
    ) {}

    public function getPage(): string
    {
        return $this->page instanceof StorefrontPage
            ? $this->page->value
            : $this->page;
    }

    public function getCallbackOrClass(): \Closure|string
    {
        return $this->callback;
    }
}
