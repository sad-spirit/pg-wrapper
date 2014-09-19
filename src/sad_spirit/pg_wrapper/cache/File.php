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
    GlobIterator;

/**
 * Cache for metadata keeping cached data in filesystem
 */
class File implements MetadataCache
{
    /**
     * Regexp pattern for cache keys, as these should map to file names
     */
    const KEY_PATTERN = '/^[a-z0-9_\+\-]+$/Di';

    /**
     * Directory for cache files
     * @var string
     */
    private $_cacheDir;

    /**
     * TTL for cache entries (in seconds), 0 means infinite
     * @var int
     */
    private $_ttl;

    /**
     * Constructor.
     *
     * @param string $cacheDir
     * @param int    $ttl
     * @throws InvalidArgumentException
     */
    public function __construct($cacheDir = null, $ttl = 0)
    {
        $this->setCacheDir($cacheDir);
        $this->setTtl($ttl);
    }

    /**
     * Sets the directory for cache files
     *
     * @param string|null $dir If null, system temporary directory will be used
     * @throws InvalidArgumentException
     */
    public function setCacheDir($dir = null)
    {
        if (null === $dir) {
            $dir = sys_get_temp_dir();

        } else {
            if (!is_dir($dir)) {
                $this->mkdir($dir);
                if (!is_dir($dir)) {
                    throw new InvalidArgumentException(
                        "Cache directory '{$dir}' is not a directory"
                    );
                }
            }
            if (!is_writable($dir)) {
                throw new InvalidArgumentException(
                    "Cache directory '{$dir}' not writable"
                );
            } elseif (!is_readable($dir)) {
                throw new InvalidArgumentException(
                    "Cache directory '{$dir}' not readable"
                );
            }

            $dir = rtrim(realpath($dir), DIRECTORY_SEPARATOR);
        }

        $this->_cacheDir = $dir;
    }

    /**
     * Returns the directory for cache files
     *
     * @return string
     */
    public function getCacheDir()
    {
        return $this->_cacheDir;
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
        $this->dumpFile($this->getFilename($key), serialize($value), 0644);
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $filename = $this->getFilename($key);
        clearstatcache();
        if (!file_exists($filename)) {
            return null;
        }
        if ($ttl = $this->getTtl()) {
            if (!($mtime = @filemtime($filename))) {
                throw new RuntimeException("Failed to get mtime for '{$filename}'");
            }
            if (time() >= $mtime + $ttl) {
                return null;
            }
        }
        if (false === ($data = @file_get_contents($filename))) {
            throw new RuntimeException("Failed to read file '{$filename}'");
        }

        return unserialize($data);
    }

    /**
     * {@inheritdoc}
     */
    public function clearByPrefix($prefix)
    {
        $prefix   = (string)$prefix;
        if ('' === $prefix) {
            throw new InvalidArgumentException('No prefix given');
        }

        $flags    = GlobIterator::SKIP_DOTS | GlobIterator::CURRENT_AS_PATHNAME;
        $path     = $this->getCacheDir() . DIRECTORY_SEPARATOR . $prefix . '*';
        $warnings = array();

        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;
            return true;
        }, E_WARNING);
        foreach (new GlobIterator($path, $flags) as $pathname) {
            unlink($pathname);
        }
        if ($warnings) {
            throw new RuntimeException("Failed to remove files of '{$path}': " . implode("\n", $warnings));
        }

        return true;
    }

    /**
     * Returns filename for a given cache key
     *
     * @param string $key
     * @return string
     */
    protected function getFilename($key)
    {
        $this->normalizeKey($key);
        return $this->getCacheDir() . DIRECTORY_SEPARATOR . $key . '.dat';
    }

    /**
     * Recursively creates a cache directory, setting permissionss without umask
     *
     * Idea borrowed from ZF2's Cache component
     *
     * @param string $path
     * @param int    $dirPerms
     * @throws RuntimeException
     */
    protected function mkdir($path, $dirPerms = 0777)
    {
        $parts = array();
        while (!file_exists($path)) {
            array_unshift($parts, basename($path));
            if ($path === ($nextPath = dirname($path))) {
                break;
            }
            $path = $nextPath;
        }

        foreach ($parts as $part) {
            $path  .= DIRECTORY_SEPARATOR . $part;
            $umask  = umask(0);
            $res    = @mkdir($path, $dirPerms, false);
            umask($umask);

            if (!$res) {
                $oct = decoct($dirPerms);
                throw new RuntimeException("Failed to create cache directory '{$path}' with mode 0{$oct}");
            }
        }
    }

    /**
     * Atomically writes a file
     *
     * Idea borrowed from Symfony2 Filesystem component / Twig
     *
     * @param  string  $filename  The file to be written to.
     * @param  string  $content   The data to write into the file.
     * @param  int     $filePerms File permissions
     * @throws RuntimeException  If the file cannot be written to.
     */
    protected function dumpFile($filename, $content, $filePerms = 0666)
    {
        $dir     = dirname($filename);
        $tmpFile = tempnam($dir, basename($filename));

        if (false === @file_put_contents($tmpFile, $content)) {
            throw new RuntimeException("Failed to write file '{$filename}'");
        }

        if (true !== @rename($tmpFile, $filename)) {
            throw new RuntimeException("Failed to rename '{$tmpFile}' to '{$filename}'");
        }

        if (true !== @chmod($filename, $filePerms)) {
            $oct = decoct($filePerms);
            throw new RuntimeException("Failed to chmod '{$filename}' to mode 0{$oct}");
        }
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

        if (!preg_match(self::KEY_PATTERN, $key)) {
            throw new InvalidArgumentException(sprintf(
                "The key '%s' doesn't match the required pattern '%s'",
                $key, self::KEY_PATTERN
            ));
        }
    }
}
