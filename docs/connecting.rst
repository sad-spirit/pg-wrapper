================================
Setting up a database connection
================================

The entrance point to the "wrapper" functionality of this package is ``sad_spirit\pg_wrapper\Connection`` class.
It represents the connection to a database, encapsulates the
`native \\PgSql\\Connection object <https://www.php.net/manual/en/class.pgsql-connection.php>`__,
and contains methods to execute queries and manage transactions.

Instances of other wrapper-related classes like ``PreparedStatement`` and ``Result`` are normally created via methods
of ``Connection``.

Establishing connection
=======================

Constructor of ``Connection`` accepts a connection string suitable for
`pg_connect() <https://www.php.net/manual/en/function.pg-connect.php>`__ /
underlying `PQconnectdb <https://www.postgresql.org/docs/current/libpq-connect.html#LIBPQ-PQCONNECTDB>`__:

.. code-block:: php

   use sad_spirit\pg_wrapper\Connection;

   // whitespace-separated keyword=value pairs
   $connection = new Connection('host=localhost port=5432 dbname=postgres');
   // URI format
   $another    = new Connection('postgresql://localhost:5432/postgres');

Connecting is lazy by default: creating the ``Connection`` object will not immediately establish a connection
to the database, it will only be established once it is needed (e.g. ``$connection->execute()`` is called).

.. note::
    Connection is established automatically only once. If an explicit ``disconnect()`` is performed,
    it will require an explicit ``connect()`` to re-establish the connection.

You can request an eager connection if you pass ``false`` as a second argument to the constructor:

.. code-block:: php

   use sad_spirit\pg_wrapper\Connection;

   $connection = new Connection('host=localhost port=5432 dbname=postgres', false);
   var_dump($connection->isConnected());

will output

.. code-block:: output

   bool(true)

The connection will be automatically closed once ``Connection`` object is destroyed.

Connection-related methods
==========================

The following additional methods of ``Connection`` help with the connection handling

``connect(): $this``
    Explicitly connects to the database. Throws ``ConnectionException`` if connection fails
    or if the connected server reports an unsupported version.

``disconnect(): $this``
    Disconnects from the database.

``isConnected(): bool``
    Checks whether the connection was established and is still usable (uses
    `pg_connection_status() <https://www.php.net/manual/en/function.pg-connection-status.php>`__ for the latter check).

``getNative(): \PgSql\Connection``
    returns the `native object
    <https://www.php.net/manual/en/class.pgsql-connection.php>`__ that represents the connection starting with PHP 8.1,
    this will call ``connect()`` in lazy connection scenario.

``getConnectionId(): string``
    Returns a unique identifier for the connection. The identifier is based
    on connection string and is used by ``converters\CachedTypeOIDMapper`` as cache key prefix for types metadata.
    May be used for manual cache invalidation as well.

``getLastError(): ?string``
    Returns the last error message for this connection, ``null`` if none present.

Configuration methods
=====================

You can additionally configure ``Connection`` by passing custom ``TypeConverterFactory``
and ``Psr\Cache\CacheItemPoolInterface`` implementations to appropriate methods.

``setTypeConverterFactory(TypeConverterFactory $factory): $this``
    Sets the factory object for converters to and from PostgreSQL representation.

``getTypeConverterFactory(): TypeConverterFactory``
    Returns the factory object for converters to and from PostreSQL representation.
    If one was not set explicitly by the previous method, sets and returns
    an instance of :ref:`a default factory <converter-factories-default>`.

.. tip::
    Using an instance of ``StubTypeConverterFactory`` will effectively disable type conversion.


``setMetadataCache(Psr\Cache\CacheItemPoolInterface $cache): $this``
    Sets the DB metadata cache. The above interface is defined in `PSR-6 <http://www.php-fig.org/psr/psr-6/>`__,
    use any compatible implementation.

``getMetadataCache(): Psr\Cache\CacheItemPoolInterface``
    Returns the DB metadata cache

Within pg_wrapper package this cache is used by ``converters\CachedTypeOIDMapper`` to store type information
loaded from database. It may be also used by other packages depending on pg_wrapper to store additional metadata,
e.g. `sad_spirit/pg_gateway <https://github.com/sad-spirit/pg-gateway>`__ uses that to store metadata
of accesses tables.

.. note::
    It is highly recommended to use the cache in production to prevent database metadata lookups
    on each page request.
