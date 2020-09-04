<?php

namespace Elegant\Events\Tests\Fixtures;

class CustomEvent
{
    public $params = [];

    public function __construct(array $params = [])
    {
        $this->params = $params;
    }
}
