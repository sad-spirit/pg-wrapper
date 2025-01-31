===================================
Adding functionality via decorators
===================================

"Decorator" is the name of `a design pattern <https://en.wikipedia.org/wiki/Decorator_pattern>`__
that allows behavior to be added to an individual object, dynamically.

pg_wrapper provides a means of adding behaviour to ``Connection`` and ``PreparedStatement`` classes.

Base classes for decorators
===========================

There are (currently) no interfaces that ``Connection`` and ``PreparedStatement`` implement, so these are created
as subclasses

.. code-block:: php

    namespace sad_spirit\pg_wrapper\decorators;

    use sad_spirit\pg_wrapper\Connection;
    use sad_spirit\pg_wrapper\PreparedStatement;

    abstract class ConnectionDecorator extends Connection
    {
        public function __construct(private readonly Connection $wrapped)
        {
        }

        // Overrides all public methods of Connection, forwarding all method calls to the $wrapped instance
    }

    abstract class PreparedStatementDecorator extends PreparedStatement
    {
        public function __construct(private readonly PreparedStatement $wrapped)
        {
        }

        // Overrides all public methods of PreparedStatement, forwarding all method calls to the $wrapped instance
    }

The extending classes will have to only override the methods whose behaviour they want to change.

Logging decorators
==================

The package contains decorators that add logging of executed queries
to a `PSR-3 compatible logger <https://www.php-fig.org/psr/psr-3/>`__.

The constructor of ``decorators\logging\Connection`` should receive an instance of ``Connection`` being decorated
and an implementation of ``Psr\Log\LoggerInterface``. The decorated ``PreparedStatement``
will be created and returned by that decorator's ``prepare()`` method.

Using this e.g. with Monolog:

.. code-block:: php

    use sad_spirit\pg_wrapper\Connection as BaseConnection;
    use sad_spirit\pg_wrapper\decorators\logging\Connection;
    use Monolog\Level;
    use Monolog\Logger;
    use Monolog\Handler\StreamHandler;

    $log = new Logger('pg_wrapper');
    $log->pushHandler(new StreamHandler('queries.log', Level::Debug));

    $connection = new Connection(new BaseConnection('...connection string...'), $log);

    $connection->execute('drop database production');

