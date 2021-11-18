<?php

/**
 * Stubs for resource replacing classes introduced in PHP 8.1
 */

namespace Pgsql;

if (\PHP_VERSION_ID < 80100) {
    final class Connection
    {
    }

    final class Result
    {
    }

    final class Lob
    {
    }
}
