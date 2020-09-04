<?php

namespace Elegant\Events\Tests\Fixtures;

use Elegant\Events\TransactionalEvent;

class CustomTransactionalEvent extends CustomEvent implements TransactionalEvent
{
}
