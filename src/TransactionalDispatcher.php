<?php

namespace Elegant\Events;

use Elegant\Events\Concerns\DelegatesToDispatcher;
use Illuminate\Support\Str;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;

class TransactionalDispatcher implements DispatcherContract
{
    use DelegatesToDispatcher;

    /**
     * The event dispatcher.
     *
     * @var DispatcherContract
     */
    protected $dispatcher;

    /**
     * All open transactions.
     *
     * @var array
     */
    protected $transactions = [];

    /**
     * The events that must have transactional behavior.
     *
     * @var array
     */
    protected $include = [];

    /**
     * The events that are not considered on transactional layer.
     *
     * @var array
     */
    protected $exclude = [];

    /**
     * Create a new transactional event dispatcher instance.
     *
     * @param  DispatcherContract  $dispatcher
     */
    public function __construct(DispatcherContract $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        $this->registerListeners();
    }

    /**
     * Set list of events that should be handled by transactional layer.
     *
     * @param  array|null  $events
     * @return void
     */
    public function includeEvents(array $events)
    {
        $this->include = $events;
    }

    /**
     * Set list of events that are not considered on transactional layer.
     *
     * @param  array|null  $events
     * @return void
     */
    public function excludeEvents(array $events)
    {
        $this->exclude = $events;
    }

    /**
     * Dispatch an event and call the listeners.
     *
     * @param  string|object $event
     * @param  mixed $payload
     * @param  bool $halt
     * @return array|null
     */
    public function dispatch($event, $payload = [], $halt = false)
    {
        if (!$halt and $this->shouldHandleEvent($event)) {
            $this->handleEvent($event, $payload);
        } else {
            return $this->dispatcher->dispatch($event, $payload, $halt);
        }
    }

    /**
     * Fires on transaction begin.
     *
     * @return void
     */
    protected function onTransactionBegin()
    {
        $this->transactions[] = new class {
            public $events = [];
        };
    }

    /**
     * Fires on transaction commit.
     *
     * @return void
     */
    protected function onTransactionCommit()
    {
        $transaction = array_pop($this->transactions);

        if (null === $transaction) {
            return;
        }

        foreach ($transaction->events as $args) {
            $this->dispatcher->dispatch(...$args);
        }
    }

    /**
     * Fires on transaction rollback.
     *
     * @return void
     */
    protected function onTransactionRollback()
    {
        array_pop($this->transactions);
    }

    /**
     * Check whether there is at least one transaction running.
     *
     * @return bool
     */
    protected function isTransactionRunning()
    {
        return count($this->transactions) > 0;
    }

    /**
     * Check whether an event should be handled by this layer or not.
     *
     * @param  string|object  $event
     * @return bool
     */
    protected function shouldHandleEvent($event)
    {
        return $this->isTransactionRunning() && $this->isTransactionalEvent($event);
    }

    /**
     * Check whether an event is a transactional event or not.
     *
     * @param  string|object $event
     * @return bool
     */
    protected function isTransactionalEvent($event)
    {
        if ($event instanceof TransactionalEvent) {
            return true;
        }

        $event = is_string($event) ? $event : get_class($event);

        foreach ($this->exclude as $excluded) {
            if ($this->matches($excluded, $event)) {
                return false;
            }
        }

        foreach ($this->include as $included) {
            if ($this->matches($included, $event)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether an event name matches a pattern or not.
     *
     * @param  string  $pattern
     * @param  string  $event
     * @return bool
     */
    protected function matches($pattern, $event)
    {
        if (Str::contains($pattern, '*')) {
            return Str::is($pattern, $event);
        } else {
            return Str::startsWith($event, $pattern);
        }
    }

    /**
     * Add a pending transactional event to the last open transaction.
     *
     * @param  string|object $event
     * @param  mixed $payload
     * @return void
     */
    protected function handleEvent($event, $payload)
    {
        $transaction = array_last($this->transactions);

        $transaction->events[] = [$event, $payload];
    }

    /**
     * Setup listeners for transaction events.
     *
     * @return void
     */
    protected function registerListeners()
    {
        $this->dispatcher->listen(TransactionBeginning::class, function () {
            $this->onTransactionBegin();
        });

        $this->dispatcher->listen(TransactionCommitted::class, function () {
            $this->onTransactionCommit();
        });

        $this->dispatcher->listen(TransactionRolledBack::class, function () {
            $this->onTransactionRollback();
        });
    }
}
