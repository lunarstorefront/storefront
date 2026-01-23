<?php

namespace Lunar\Storefront;

class PropData
{
    public function __construct(
        public string $page,
        public string $key,
        public \Closure|string $callback,
    )
    {

    }

    public function getPage(): string
    {
        return $this->page;
    }

    public function getCallbackOrClass(): \Closure|string
    {
        return $this->callback;
    }
}
