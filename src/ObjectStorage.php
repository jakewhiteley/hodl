<?php

namespace Hodl;

use Hodl\Exceptions\InvalidKeyException;
use Hodl\Exceptions\KeyExistsException;

/**
 * Stored objects for Hodl.
 */
class ObjectStorage
{
    /**
     * Stores a map of the registered keys and their closures.
     */
    private array $definitions = [
        'instance' => [],
        'factory' => [],
    ];

    /**
     * Map of aliases.
     */
    private array $aliases = [];

    /**
     * Stores initialized objects.
     */
    private array $store = [];

    /**
     * Add an object definition.
     *
     * @throws \Hodl\Exceptions\InvalidKeyException
     * @throws \Hodl\Exceptions\KeyExistsException
     */
    public function object(string $key, callable $closure): void
    {
        $this->checkKey($key);
        $this->definitions['instance'][$key] = $closure;
    }

    /**
     * Add a factory definition.
     *
     * Like an object, but a new instance is returned when accessed.
     *
     * @throws \Hodl\Exceptions\InvalidKeyException
     * @throws \Hodl\Exceptions\KeyExistsException
     */
    public function factory(string $key, callable $closure): void
    {
        $this->checkKey($key);
        $this->definitions['factory'][$key] = $closure;
    }

    /**
     * Add an instance definition.
     *
     * @throws \Hodl\Exceptions\InvalidKeyException
     * @throws \Hodl\Exceptions\KeyExistsException
     */
    public function instance(string $key, object $object): void
    {
        $this->checkKey($key);
        $this->definitions['instance'][$key] = true;
        $this->store[$key] = $object;
    }

    /**
     * Boots up an object and stores.
     */
    public function store(string $key, object $object): void
    {
        if (isset($this->aliases[$key])) {
            $key = $this->aliases[$key];
        }

        $this->store[$key] = $object;
    }

    /**
     * Checks if the given key has an object definition.
     */
    public function hasObject(string $key): bool
    {
        if (isset($this->definitions['instance'][$key])) {
            return true;
        }

        if (isset($this->aliases[$key])) {
            return isset($this->definitions['instance'][$this->aliases[$key]]);
        }

        return false;
    }

    /**
     * Checks if the given key has a factory definition.
     */
    public function hasFactory(string $key): bool
    {
        if (isset($this->definitions['factory'][$key])) {
            return true;
        }

        if (isset($this->aliases[$key])) {
            return isset($this->definitions['factory'][$this->aliases[$key]]);
        }

        return false;
    }

    /**
     * Checks if the given key has already been booted and stored.
     */
    public function hasStored(string $key): bool
    {
        if (isset($this->store[$key])) {
            return true;
        }

        if (isset($this->aliases[$key])) {
            return isset($this->store[$this->aliases[$key]]);
        }

        return false;
    }

    /**
     * Returns a raw object definition.
     */
    public function getDefinition(string $key): callable
    {
        if (isset($this->definitions['instance'][$key])) {
            return $this->definitions['instance'][$key];
        }

        return $this->definitions['instance'][$this->aliases[$key]];
    }

    /**
     * Returns a stored object.
     */
    public function getStored(string $key): object
    {
        return $this->store[$key] ?? $this->store[$this->aliases[$key]];
    }

    /**
     * Returns a booted factory definition.
     */
    public function getFactory(string $key): callable
    {
        return $this->definitions['factory'][$key] ?? $this->definitions['factory'][$this->aliases[$key]];
    }

    /**
     * Removes a given key and all data from the storage.
     */
    public function remove(string $key): bool
    {
        // check if the $key is an alias
        if (isset($this->aliases[$key])) {
            $alias = $key;
            $key = $this->aliases[$key];
            unset($this->aliases[$alias]);
        }

        // if the key exists as a factory
        if ($this->hasFactory($key)) {
            unset($this->definitions['factory'][$key]);
            $this->removeAliasFor($key);
            return !$this->hasFactory($key);
        }

        // if the key exists as an object
        if ($this->hasObject($key)) {
            unset($this->definitions['instance'][$key], $this->store[$key]);
            $this->removeAliasFor($key);
            return !($this->hasObject($key) || $this->hasStored($key));
        }

        // the key did not exist
        return false;
    }

    /**
     * Remove just an alias or binding, leaving the object and key intact.
     */
    public function removeAlias(string $alias): bool
    {
        if (isset($this->aliases[$alias])) {
            unset($this->aliases[$alias]);
            return true;
        }

        return false;
    }

    /**
     * Bind a given service to an alias.
     */
    public function addAlias(string $key, string $alias): void
    {
        $this->aliases[$alias] = $key;
    }

    /**
     * Remove all aliases for a given key.
     *
     * The key can be the original classname, or an alias for that class.
     */
    protected function removeAliasFor(string $key): void
    {
        $aliases = \array_keys($this->aliases, $key);

        if (!empty($aliases)) {
            foreach ($aliases as $alias) {
                unset($this->aliases[$alias]);
            }
        }
    }

    /**
     * Performs some safety checks on a key when adding to the container.
     *
     * @throws \Hodl\Exceptions\KeyExistsException  If the key already exists.
     * @throws \Hodl\Exceptions\InvalidKeyException If the key is not a valid class name.
     */
    protected function checkKey(string $key): void
    {
        if (!\class_exists($key)) {
            throw new InvalidKeyException("Key [$key] was invalid. All keys must be valid class names");
        }

        if ($this->hasObject($key) || $this->hasFactory($key)) {
            throw new KeyExistsException("Key [$key] already exists within the container");
        }
    }
}
