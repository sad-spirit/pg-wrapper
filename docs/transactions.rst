.. _transactions:

============
Transactions
============

While ``Connection`` has :ref:`usual methods for controlling transactions and savepoints <transactions-methods-manual>`,
the suggested approach is to use ``atomic()`` method. It takes a callable and executes it atomically, reducing
the need for boilerplate code.

Running a callable atomically
=============================

Using ``atomic()`` to execute a callable

.. code-block:: php

   $connection->atomic(function () {
       // do some stuff
   });

is roughly equivalent to

.. code-block:: php

   $connection->beginTransaction();
   try {
       // do some stuff
       $connection->commit();
   } catch (\Throwable $e) {
       $connection->rollback();
       throw $e;
   }

``atomic()`` calls can be nested, the inner call may create a savepoint
(this behaviour is controlled by a second argument to ``atomic()`` and is disabled by default) and
thus be rolled back without affecting the whole transaction:

.. code-block:: php

   // note that connection object will be passed as an argument to callback
   $stored = $connection->atomic(function (Connection $connection) {
       storeSomeRecords();
       try {
           // We know that the function may fail due to some unique constraint violation
           // and are perfectly fine with that, so request a savepoint for inner atomic block 
           $connection->atomic(function () {
               populateSomeDictionaries();
           }, true);
       } catch (ConstraintViolationException $e) {
           // even if the inner atomic() failed the outer atomic may proceed
       }
       return storeSomethingElse();
   });

.. note::
    The example above shows the correct way to catch errors with atomic, that is *around* ``atomic()`` call.
    As ``atomic()`` looks at exceptions to know whether callback succeeded or failed, catching and
    handling exceptions around individual queries will break that logic. If necessary, add another ``atomic()``
    call for these queries.

Internally ``atomic()`` does the following

- opens a transaction in the outermost ``atomic()`` call;
- creates a savepoint when entering an inner ``atomic()`` call;
- performs a callback, whatever it returns will be returned by ``atomic()``;
- releases or rolls back to the savepoint when exiting an inner call;
- commits or rolls back the transaction when exiting the outermost call.

If savepoint wasn't created for an inner call, ``atomic()`` will perform
the rollback when exiting the first parent call with a savepoint if
there is one, and the outermost call otherwise.

.. note::
    If a transaction was already open before an outermost ``atomic()`` call made with ``$savepoint = false``,
    it will not be committed or rolled back on exit, you'll have to do it explicitly. If an error happens,
    ``atomic()`` will, however, mark the transaction "for rollback only".

Performing actions after transaction
====================================

Sometimes you need to perform an action related to the current database transaction, but only if the transaction
successfully commits, e.g. send an email notification, or invalidate a cache. You may also need to do
some cleanup after a rollback.

``Connection`` has methods for registering callbacks that will run after
commit and rollback: ``onCommit()`` and ``onRollback()``. You can only
use these methods inside ``atomic()``, outside you'll get
``BadMethodCallException``.

.. code-block:: php

   $connection->atomic(function (Connection $connection) {
       $connection->onCommit(function () {
           sendAnEmail();
           resetACache();
       });
       $connection->onRollback(function () {
           resetSomeModelProperties();
           clearSomeFiles();
       });
   });

Savepoints created by nested ``atomic()`` calls are handled correctly.
If inner ``atomic()`` call fails, and the transaction is rolled back to
savepoint, then ``onCommit()`` callbacks registered within that call and
nested ``atomic()`` calls will not run after transaction commit. Their
``onRollback()`` callbacks will run instead.

Callbacks are executed *outside the transaction* after a commit or
rollback. This means that an error in ``onCommit()`` callback will not
cause a rollback.

.. note::
    While ``Connection`` takes reasonable precautions to run ``onRollback()`` callbacks in case of implicit rollback
    (lost connection to database while in transaction, script ``exit()`` while in transaction), it is possible that
    the script terminates in such a way that callbacks will not run.

.. _transactions-methods:

Transaction-related methods of ``Connection``
=============================================

There are two distinct groups of methods in ``Connection`` that are related to transactions:

- Methods that are used to manually start and finish them. These are either outright unusable in ``atomic()`` closures
  or should be used with caution;
- Those related to ``atomic()``, most of these will throw a ``BadMethodCallException`` if called outside ``atomic()``.


.. _transactions-methods-manual:

Methods for manual transaction handling
---------------------------------------

``public function beginTransaction(): $this``
    Starts a transaction. Will throw a ``BadMethodCallException`` if called within ``atomic()`` block.

``public function commit(): $this``
    Commits a transaction. Will throw a ``BadMethodCallException`` if called within ``atomic()`` block.

``public function rollback(): $this``
    Rolls back a transaction. Will throw a ``BadMethodCallException`` if called within ``atomic()`` block.

``public function inTransaction(): bool``
    Checks whether a transaction is currently open.

``public function createSavepoint(string $savepoint): $this``
    Creates a new savepoint with the given name. Will throw a ``RuntimeException`` if called outside a transaction.

``public function releaseSavepoint(string $savepoint): $this``
    Releases the given savepoint. Will throw a ``RuntimeException`` if called outside a transaction.

``public function rollbackToSavepoint(string $savepoint): $this``
    Rolls back to the given savepoint. Will throw a ``RuntimeException`` if called outside a transaction.


Methods related to ``atomic()``
-------------------------------

``public function atomic(callable $callback, bool $savepoint = false): mixed``
    Runs the given ``$callback`` atomically, returns the value returned by it. ``$callback`` receives the ``Connection``
    instance as an argument.

    Before running ``$callback`` ``atomic()`` ensures the transaction is started and creates a savepoint on nested call
    if ``$savepoint`` is ``true``. Since savepoints add a bit of overhead, their creation is disabled by default.

    If ``$callback`` executes normally then transaction is committed or savepoint is released. In case of exception
    the transaction is rolled back (maybe to savepoint) and exception is re-thrown.

``public function onCommit(callable $callback): $this``
    Registers a callback that will execute when the transaction is committed. Throws a
    ``BadMethodCallException`` if called outside ``atomic()``.

``public function onRollback(callable $callback): $this``
    Registers a callback that will execute when the transaction is rolled back. Throws a
    ``BadMethodCallException`` if called outside ``atomic()``.

``public function setNeedsRollback(bool $needsRollback): $this``
    Sets/resets the ``$needsRollback`` flag for the current transaction. If this flag is set, then the only possible
    outcome for the current transaction is ``rollback()``, attempts to perform other queries will cause
    exceptions. ``atomic()`` uses this flag internally to signal to the outer call that the inner call failed.

    This method should only be used when doing some custom error handling in ``atomic()`` and will raise a
    ``BadMethodCallException`` if used outside.

``public function needsRollback(): bool``
    Returns the above ``$needsRollback`` flag, specifying whether transaction should be rolled back due to an error
    in an inner block.

``public function assertRollbackNotNeeded(): void``
    Throws an exception if ``$needsRollback`` flag was previously set. This is used by query execution methods to
    prevent queries except ``ROLLBACK``.
