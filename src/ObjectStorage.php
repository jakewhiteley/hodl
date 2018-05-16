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
        return isset($this->definitions['instance'][$key]);
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
        return isset($this->definitions['factory'][$key]);
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
        return isset($this->store[$key]);
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
        return $this->definitions['instance'][$key];
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
        return $this->definitions['factory'][$key]();
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
        // if the key exists as a factory
        if ($this->hasFactory($key)) {
            unset($this->definitions['factory'][$key]);
            return ! $this->hasFactory($key);
        }

        // if the key exists as an object
        if ($this->hasObject($key)) {
            unset($this->definitions['instance'][$key], $this->store[$key]);
            return ! $this->hasObject($key);
        }

        // the key did not exist
        return false;
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
