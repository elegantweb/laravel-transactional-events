# laravel-transactional-events

> Transactional events for Laravel framework.

### Installation

``` bash
composer require elegantweb/laravel-transactional-events
```

### Why?

Take a look at the example below:

``` php
DB::transaction(function () {
    $post = Post::create(['title' => 'An Awesome Post!']);
    event(new PostCreated($post));
    $post->categories()->create(['name' => 'Blog']);
});
```

At the first glance, everything seems fine, but there is a big problem here!

Imagine we are sending a notification inside `PostCreated` event, let see what can happen:

**Situation 1:** The notification fails to be sent, probably an exception will be thrown,
the transaction will be rolled back and the created post will be removed from database.
What actually happened is, our post got removed because of the notification failure, Ask yourself, "Is this what I want?! 🤔"
Most of the time, we don't want to remove the post because of failed notifications!

**Situation 2:** Lets consider that the `PostCreated` event dispatched successfully without any error. but because of some database or application problem, we got an error
when we tried to create a category for our post (How unlucky we are!!! 😩), in this case, the transaction will be fail and the post will be removed from database.
So far so good, but guess what?! we have already sent a notification inside the `PostCreated` event for the post that no longer exists! 😱

**Solution:** The workaround here is to make the event to dispatch after the transaction commitment.
Using this package, you can make the event transactional, so the event will be postponed until the commitment of the transaction.

### Usage

The package is enabled out of the box.
What you need to do is to just make your events transactional.

#### With Transactional Interface

One possible way to make an event transactional is to implements
`Elegant\Events\TransactionalEvent` interface.
Take a look at the example below:

``` php
use Elegant\Events\TransactionalEvent;

class MyAwesomeEvent implements TransactionalEvent
{
}
```

Now the `MyAwesomeEvent` class is transactional and it will be handled by the package whenever you dispatch it.

#### With Configuration

The other way is to use configuration file. This way, You can make a group of events transactional.

First of all publish the default config file:

``` bash
php artisan vendor:publish --tag="laravel-transactional-events-config"
```

We have two options, `include` and `exclude`.

Using `include`, you can make an event class or a group of events under certain namespace transactional.

By default we have `App\Events` namespace, this will cause all events under `App\Events` namespace to became transactional.

```php
return [
    'include' => [
        'App\Events',
    ],
];
```

The `exclude` option is the opposite of the `include` option, you can exclude an event class or group of events under certain namespace from being transactional.

The example below will cause all events under `App\Events` namespace to become transactional except for `App\Events\MyAwesomeEvent` class.

```php
return [
    'include' => [
        'App\Events',
    ],

    'exclude' => [
        'App\Events\MyAwesomeEvent',
    ],
];
```
