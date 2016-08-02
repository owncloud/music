# ngQueue

ngQueue is an AngularJS module that helps you to handle routine sync/async queue task in AngularJS with ease.

#### [Demo](http://plnkr.co/edit/qudYr8?p=preview)


## Getting started

Include the ngQueue module with AngularJS script in your page.

```html
<script src="//ajax.googleapis.com/ajax/libs/angularjs/1.1.5/angular.min.js"></script>
<script src="http://pc035860.github.io/ngQueue/ngQueue.min.js"></script>
```

Add `ngQueue` to your app module's dependency.

```js
angular.module('myApp', ['ngQueue']);
```

### Install with Bower

```sh
bower install ngQueue
```

## Usage

### $queueFactory(limit, deferred)

* `limit` - The maximum concurrent task for the queue. Default value `1`.
* `deferred` - If `true`, queued tasks are padded with a very small time interval, allowlling the browser to update UIs or respond to user interactions. Default value `false`. [More about `deferred` option](#the-deferred-queue).

Start with `$queueFactory` to create a queue you'll be working with.

```js
// Create a queue with concurrent task quota of 2
var queue1 = $queueFactory(2);

// Create a deferred queue
var queue2 = $queueFactory(1, true);
```

Now you are ready to play with `Queue` instance APIs.

### Queue instance APIs

#### enqueue(fn, context, args)

Enqueue a `task(fn)` with specified `context(optional)` and `arguments(optional)`. Returns a task handle for you to remove the task from the queue before it gets dequeued with [`remove`](#removetaskhandle).

**The task can be either asynchronous or synchronous.**

Synchronous tasks work just like usual function call.

```js
//////////////////////
// synchronous task //
//////////////////////
queue.enqueue(function (inA, inB, inC) {

  console.log(this);  // {name: "context"}
  
  console.log(inA, inB, inC);  // hello world !
  
  doSomething();
  
}, {name: 'context'}, ['hello', 'world', '!']);
```

Return an [$q promise](http://code.angularjs.org/1.1.5/docs/api/ng.$q) in the task function to make it asynchronous.

```js
///////////////////////
// asynchronous task //
///////////////////////

// $timeout delay
queue.enqueue(function () {
  var dfd = $q.defer();

  $timeout(function () {
    dfd.resolve();
    // or dfd.reject()
  }, 100);

  return dfd.promise;
});

// $http request
queue.enqueue(function () {

  return $http.get('/some/api/call')
    .success(function () {
      // do something if success
    })
    .error(function () {
      // do something if error
    });

});
```

#### remove(taskHandle)

Remove a task from the queue before it gets dequeued. `taskHandle` is what returned by `enqueue()`.

#### clear()

Clear the queue.

#### dequeue()

Try to dequeue manually. In most cases, the queue will handle all the dequeue works itself.


## The `deferred` queue

Here's a nice reading by Nicholas C. Zakas

http://www.nczonline.net/blog/2013/07/09/the-case-for-setimmediate/

The basic idea of the `deferred` option of `$queueFactory` is to utilize `setImmediate()` API to ease the browser freezing problems which we sometimes encountered when dealing with heavy-load tasks. Though it's not implemented on every browser, if you kindly provide a [polyfill](https://github.com/NobleJS/setImmediate), we're still good.

In the condition that `ngQueue` can't find the `setImmeidate()` API, it'll use `$timeout` of AngularJS instead.
