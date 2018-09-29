<?php

namespace Hodl;

use Hodl\Exceptions\InvalidKeyException;
use Hodl\Exceptions\KeyExistsException;
use Hodl\Exceptions\NotFoundException;

/**
 * Stored objects for Hodl.
 */
class ObjectStorage
{
    /**
     * Stores a map of the registered keys and their closures.
     * @var array
     */
    private $definitions = [
        'instance' => [],
        'factory'  => [],
    ];

    /**
     * Map of aliases.
     * @var array
     */
    private $aliases = [];

    /**
     * Stores initialized objects.
     * @var array
     */
    private $store = [];

    /**
     * Add an object definition.
     *
     * @since 1.0.0
     *
     * @param  string   $key     Object key.
     * @param  callable $closure Definition for this object.
     */
    public function object(string $key, callable $closure)
    {
        $this->checkKey($key);
        $this->definitions['instance'][$key] = $closure;
    }

    /**
     * Add a factory definition.
     *
     * Like an object, but a new instance is returned when accessed.
     *
     * @since 1.0.0
     *
     * @param  string   $key     Factory key.
     * @param  callable $closure Definition for this factory.
     */
    public function factory(string $key, callable $closure)
    {
        $this->checkKey($key);
        $this->definitions['factory'][$key] = $closure;
    }

    /**
     * Add an instance definition.
     *
     * @since 1.0.0
     *
     * @param  string   $key    Instance key.
     * @param  object   $object Definition for this instance.
     */
    public function instance(string $key, $object)
    {
        $this->checkKey($key);
        $this->definitions['instance'][$key] = true;
        $this->store[$key] = $object;
    }

    /**
     * Boots up an object and stores.
     *
     * @since 1.0.0
     *
     * @param  string   $key    Object key.
     * @param  object   $object Definition for this instance.
     */
    public function store(string $key, $object)
    {
        $this->store[$key] = $object;
    }

    /**
     * Checks if the given key has an object definition.
     *
     * @since 1.0.0
     *
     * @param  string  $key Object key.
     * @return boolean
     */
    public function hasObject(string $key)
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
     *
     * @since 1.0.0
     *
     * @param  string  $key Object key.
     * @return boolean
     */
    public function hasFactory(string $key)
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
     *
     * @since 1.0.0
     *
     * @param  string  $key Object key.
     * @return boolean
     */
    public function hasStored(string $key)
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
     *
     * @since 1.0.0
     *
     * @param  string  $key Object key.
     * @return callable
     */
    public function getDefinition($key)
    {
        if (isset($this->definitions['instance'][$key])) {
            return $this->definitions['instance'][$key];
        }

        return $this->definitions['instance'][$this->aliases[$key]];
    }

    /**
     * Returns a stored object.
     *
     * @since 1.0.0
     *
     * @param  string  $key Object key.
     * @return object
     */
    public function getStored(string $key)
    {
        return $this->store[$key];
    }

    /**
     * Returns a booted factory definition.
     *
     * @since 1.0.0
     *
     * @param  string  $key Object key.
     * @return object
     */
    public function getFactory(string $key)
    {
        if (isset($this->definitions['factory'][$key])) {
            return $this->definitions['factory'][$key];
        }

        return $this->definitions['factory'][$this->aliases[$key]];
    }

    /**
     * Removes a given key and all data from the storage.
     *
     * @since 1.0.0
     *
     * @param  string $key Key to remove.
     * @return bool
     */
    public function remove(string $key)
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
            return ! $this->hasFactory($key);
        }

        // if the key exists as an object
        if ($this->hasObject($key)) {
            unset($this->definitions['instance'][$key], $this->store[$key]);
            $this->removeAliasFor($key);
            return ! ($this->hasObject($key) || $this->hasStored($key));
        }

        // the key did not exist
        return false;
    }

    /**
     * Bind a given service to an alias.
     *
     * @since  1.3.0
     *
     * @param  string $key   The service key to attach the alias to.
     * @param  string $alias The alias to attach.
     */
    public function addAlias($key, $alias)
    {
        $this->aliases[$alias] = $key;
    }

    /**
     * Remove all aliases for a given key.
     *
     * The key can be the original classname, or an alias for that class.
     *
     * @since  1.3.0
     *
     * @param  string $key The key to remove the aliases for.
     */
    protected function removeAliasFor($key)
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
     * @since 1.0.0
     *
     * @throws Hodl\Exceptions\InvalidKeyException if the key if not a valid class name.
     * @throws Hodl\Exceptions\KeyExistsException if the key already exists.
     *
     * @param  string $key The key to check.
     */
    protected function checkKey(string $key)
    {
        if (! \class_exists($key)) {
            throw new InvalidKeyException("Key [$key] was invalid. All keys must be valid class names");
        }

        if ($this->hasObject($key) || $this->hasFactory($key)) {
            throw new KeyExistsException("Key [$key] already exists within the container");
        }
    }
}
