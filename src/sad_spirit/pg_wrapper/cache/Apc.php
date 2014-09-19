<?php
/**
 * Wrapper for PHP's pgsql extension providing conversion of complex DB types
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\cache;

use sad_spirit\pg_wrapper\MetadataCache,
    sad_spirit\pg_wrapper\exceptions\RuntimeException,
    sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    APCIterator;

/**
 * Cache for metadata keeping cached data in APC
 */
class Apc implements MetadataCache
{
    /**
     * Namespace for cached items
     * @var string
     */
    private $_namespace;

    /**
     * TTL for cache entries (in seconds), 0 means infinite
     * @var int
     */
    private $_ttl;

    public function __construct($namespace = 'postgres', $ttl = 0)
    {
        $this->setNamespace($namespace);
        $this->setTtl($ttl);
    }

    /**
     * Sets namespace for cached items
     *
     * @param string $namespace
     */
    public function setNamespace($namespace)
    {
        $this->_namespace = (string)$namespace;
    }

    /**
     * Returns namespace for cached items
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->_namespace;
    }

    /**
     * Sets TTL for cache entries
     *
     * @param int $ttl
     * @throws InvalidArgumentException
     */
    public function setTtl($ttl)
    {
        if (0 > $ttl) {
            throw new InvalidArgumentException("TTL can't be negative");
        }
        $this->_ttl = (int)$ttl;
    }

    /**
     * Returns TTL for cache entries
     *
     * @return int
     */
    public function getTtl()
    {
        return $this->_ttl;
    }

    /**
     * {@inheritdoc}
     */
    public function setItem($key, $value)
    {
        $this->normalizeKey($key);

        $namespace   = $this->getNamespace();
        $prefix      = ('' === $namespace) ? '' : $namespace . ':';

        if (!apc_store($prefix . $key, $value, $this->getTtl())) {
            throw new RuntimeException(
                "Failed to store data in APC under key '{$prefix}{$key}"
            );
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $this->normalizeKey($key);

        $namespace   = $this->getNamespace();
        $prefix      = ('' === $namespace) ? '' : $namespace . ':';
        $result      = apc_fetch($prefix . $key, $success);

        if (!$success) {
            return null;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function clearByPrefix($prefix)
    {
        $prefix = (string)$prefix;
        if ('' === $prefix) {
            throw new InvalidArgumentException('No prefix given');
        }

        $namespace = $this->getNamespace();
        $nsPrefix  = ($namespace === '') ? '' : $namespace . ':';
        $pattern = '/^' . preg_quote($nsPrefix . $prefix, '/') . '/';
        return apc_delete(new APCIterator('user', $pattern, 0, 1, APC_LIST_ACTIVE));
    }

    /**
     * Sanity check for cache key
     *
     * @param $key
     * @throws InvalidArgumentException
     */
    protected function normalizeKey(&$key)
    {
        $key = (string)$key;

        if ('' === $key) {
            throw new InvalidArgumentException("Empty cache key isn't allowed");
        }
    }
}