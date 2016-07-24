/*jshint undef:true*/
/*global angular*/
angular.module('ngQueue', [])

.factory('$queueFactory', [
         '$q', '$window', '$timeout', '$rootScope',
function ($q,   $window,   $timeout,   $rootScope) {

  var Queue = function Queue(config) {
    this.init(config);
  };

  var p = Queue.prototype;

  // number of simultaneously runnable tasks
  p._limit = null;

  // the queue
  p._queue = null;

  // function used for deferring
  p._deferFunc = null;

  p.init = function (config) {
    this._limit = config.limit;
    this._queue = [];

    if (config.deferred) {
      if ($window.setImmediate) {
        this._deferFunc = function (todo) {
          $window.setImmediate(function () {
            todo();
            $rootScope.$apply();
          });
        };
      }
      else {
        this._deferFunc = function (todo) {
          // $timeout(todo, 0, true);
          $timeout(todo);
        };
      }
    }
  };

  p.enqueue = function (todo, context, args) {
    var task = [todo, context, args];

    this._queue.push([todo, context, args]);

    this.dequeue();
    return task;
  };

  p.remove = function (task) {
    var index = this._queue.indexOf(task);
    return this._queue.splice(index, 1)[0];
  };

  p.dequeue = function () {
    if (this._limit <= 0 || this._queue.length === 0) {
      return;
    }

    this._limit--;

    var buf = this._queue.shift(),
        todo = buf[0],
        context = buf[1],
        args = buf[2],
        success, error;

    var that = this;
    success = error = function () {
      that._limit++;

      if (that._deferFunc) {
        that._deferFunc(function () {
          that.dequeue();
        });
      }
      else {
        that.dequeue();
      }
    };

    $q.when(todo.apply(context || null, args || null))
    .then(success, error);
  };

  p.clear = function () {
    this._queue.length = 0;
  };

  return function factory(limit, deferred) {
    var config;

    if (angular.isObject(limit)) {
      config = limit;
    }
    else {
      limit = limit || 1;

      if (angular.isUndefined(deferred)) {
        deferred = false;
      }
      config = {
        limit: limit,
        deferred: !!deferred
      };
    }

    return new Queue(config);
  };
}]);
