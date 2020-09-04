<?php

namespace Elegant\Events\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Elegant\Events\Tests\Fixtures\CustomEvent;
use Elegant\Events\Tests\Fixtures\CustomTransactionalEvent;

class EventDispatcherTest extends TestCase
{
    public function test_include()
    {
        $order = [];

        Event::includeEvents([
            'Elegant\Events\Tests',
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

    public function test_exclude()
    {
        $order = [];

        Event::includeEvents([
            'Elegant\Events\Tests',
        ]);

        Event::excludeEvents([
            'Elegant\Events\Tests\Fixtures\CustomEvent',
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

    public function test_halt()
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
    
    public function test_rollback()
    {
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

    public function test_nested()
    {
        $order = [];

        Event::listen(CustomTransactionalEvent::class, function () use (&$order) {
            $order[] = 'listener';
        });

        $callNested = function ($depth) use (&$order) {
            DB::transaction(function () use (&$order, $depth) {
                $order[] = "start of t{$depth}";
                event(new CustomTransactionalEvent());
                $order[] = "end of t{$depth}";
            });
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
}
