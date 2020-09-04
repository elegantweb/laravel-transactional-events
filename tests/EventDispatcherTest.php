<?php

namespace Elegant\Events\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Elegant\Events\TransactionalDispatcher;
use Elegant\Events\Tests\Fixtures\CustomEvent;
use Elegant\Events\Tests\Fixtures\CustomTransactionalEvent;

class EventDispatcherTest extends TestCase
{
    public function test_enabled_by_default()
    {
        $this->assertEquals(TransactionalDispatcher::class, get_class($this->app['events']));
    }

    public function include_patterns_provider()
    {
        return [
            'namespace' => ['Elegant\Events\Tests'],
            'class' => ['Elegant\Events\Tests\Fixtures\CustomEvent'],
            'pattern' => ['Elegant\Events\Tests\*'],
        ];
    }

    /**
     * @dataProvider include_patterns_provider
     */
    public function test_include_patterns($pattern)
    {
        $order = [];

        Event::includeEvents([
            $pattern,
        ]);

        Event::listen(CustomEvent::class, function () use (&$order) {
            $order[] = 'listener';
        });

        DB::transaction(function () use (&$order) {
            $order[] = 'before event';
            event(new CustomEvent());
            $order[] = 'after event';
        });

        $this->assertEquals(
            ['before event', 'after event', 'listener'],
            $order,
        );
    }

    public function exclude_patterns_provider()
    {
        return [
            'namespace' => ['Elegant\Events\Tests'],
            'class' => ['Elegant\Events\Tests\Fixtures\CustomEvent'],
            'pattern' => ['Elegant\Events\Tests\*'],
        ];
    }

    /**
     * @dataProvider include_patterns_provider
     */
    public function test_exclude_patterns($pattern)
    {
        $order = [];

        Event::includeEvents([
            'Elegant\Events\Tests',
        ]);

        Event::excludeEvents([
            $pattern,
        ]);

        Event::listen(CustomEvent::class, function () use (&$order) {
            $order[] = 'listener';
        });

        DB::transaction(function () use (&$order) {
            $order[] = 'before event';
            event(new CustomEvent());
            $order[] = 'after event';
        });

        $this->assertEquals(
            ['before event', 'listener', 'after event'],
            $order,
        );
    }

    public function test_transactional_event()
    {
        $order = [];

        Event::listen(CustomTransactionalEvent::class, function () use (&$order) {
            $order[] = 'listener';
        });

        DB::transaction(function () use (&$order) {
            $order[] = 'before event';
            event(new CustomTransactionalEvent());
            $order[] = 'after event';
        });

        $this->assertEquals(
            ['before event', 'after event', 'listener'],
            $order,
        );
    }

    public function test_early_dispatch_outside_of_transactions()
    {
        $order = [];

        Event::listen(CustomTransactionalEvent::class, function () use (&$order) {
            $order[] = 'listener';
        });

        event(new CustomTransactionalEvent());

        $this->assertEquals(
            ['listener'],
            $order,
        );
    }

    public function test_halt_causes_early_dispatch()
    {
        $order = [];

        Event::listen(CustomTransactionalEvent::class, function () use (&$order) {
            $order[] = 'listener';
        });

        DB::transaction(function () use (&$order) {
            $order[] = 'before event';
            event(new CustomTransactionalEvent(), [], true);
            $order[] = 'after event';
        });

        $this->assertEquals(
            ['before event', 'listener', 'after event'],
            $order,
        );
    }

    public function test_rollback_prevents_dispatch()
    {
        $this->expectException(\Exception::class);

        $order = [];

        Event::listen(CustomTransactionalEvent::class, function () use (&$order) {
            $order[] = 'listener';
        });

        DB::transaction(function () use (&$order) {
            $order[] = 'before event';
            event(new CustomTransactionalEvent());
            throw new \Exception();
            $order[] = 'after event';
        });

        $this->assertEquals(
            ['before event'],
            $order,
        );
    }

    public function test_nested_transactions()
    {
        $order = [];

        Event::listen(CustomTransactionalEvent::class, function () use (&$order) {
            $order[] = 'listener';
        });

        $callNested = function ($depth) use (&$order) {
            DB::beginTransaction();
            $order[] = "start of t{$depth}";
            event(new CustomTransactionalEvent());
            $order[] = "end of t{$depth}";
            DB::commit();
        };

        DB::transaction(function () use (&$order, $callNested) {
            $order[] = 'start of t0';
            $callNested(1);
            $order[] = 'end of t0';
        });

        $this->assertEquals(
            ['start of t0', 'start of t1', 'end of t1', 'end of t0', 'listener'],
            $order,
        );
    }

    public function test_child_rollback_does_not_prevent_parent_dispatch()
    {
        $order = [];

        Event::listen(CustomTransactionalEvent::class, function () use (&$order) {
            $order[] = 'listener';
        });

        $callNested = function ($depth) use (&$order) {
            DB::beginTransaction();
            $order[] = "t{$depth}";
            DB::rollBack();
        };

        DB::transaction(function () use (&$order, $callNested) {
            $order[] = 'start of t0';
            event(new CustomTransactionalEvent());
            $callNested(1);
            $order[] = 'end of t0';
        });

        $this->assertEquals(
            ['start of t0', 't1', 'end of t0', 'listener'],
            $order,
        );
    }

    public function test_dispatch_order()
    {
        $order = [];

        Event::listen(CustomTransactionalEvent::class, function ($event) use (&$order) {
            $order[] = "listener {$event->params['n']}";
        });

        $callNested = function ($depth) use (&$callNested) {
            DB::beginTransaction();
            if ($depth < 2) $callNested($depth + 1);
            event(new CustomTransactionalEvent(['n' => "{$depth}0"]));
            DB::commit();
        };

        DB::transaction(function () use ($callNested) {
            event(new CustomTransactionalEvent(['n' => "00"]));
            $callNested(1);
            event(new CustomTransactionalEvent(['n' => "01"]));
        });

        $this->assertEquals(
            ['listener 00', 'listener 20', 'listener 10', 'listener 01'],
            $order,
        );
    }
}
