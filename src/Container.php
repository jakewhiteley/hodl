<?php
namespace Hodl;

use Psr\Container\ContainerInterface;

/**
 * A simple DI container
 */
class Container implements \ArrayAccess, ContainerInterface
{
    /**
     * Stores a map of the registered keys and their closures
     * @var array
     */
    private $map = [
        'persistent' => [],
        'factory'    => [],
    ];

    /**
     * Stores initialised objects
     * @var array
     */
    private $store = [];

    public $resolutionMap = [];

    /**
     * Add a class.
     * This class is initialised when first retrieved via get(), and is persistent unless explicitly destroyed
     *
     * @param string   $key     The key to store the object under
     * @param callable $closure A closure which returns a new instance of the desired object
     *                          A reference to this DIContainer is passed as a param to the closure
     */
    public function add(string $key, callable $closure)
    {
        $this->map['persistent'][$key] = $closure;
    }

    /**
     * Add a factory class.
     * Classes added via this method will return as a new instance when retrieved
     *
     * @param string   $key     The key to store the object under
     * @param callable $closure A closure which returns a new instance of the desired object
     *                          A reference to this DIContainer is passed as a param to the closure
     */
    public function addFactory(string $key, callable $closure)
    {
        $this->map['factory'][$key] = $closure;
    }

    /**
     * Check if a given key exists within this container, either as an object or a factory
     *
     * @param  string  $key The key to check for
     * @return boolean      If the key exists
     */
    public function has($key)
    {
        return $this->hasObject($key) || $this->hasFactory($key);
    }

    /**
     * Check if a given key exists as an object within this container
     *
     * @param  string  $key The key to check for
     * @return boolean      If the key exists
     */
    public function hasObject($key)
    {
        return isset($this->map['persistent'][$key]);
    }

    /**
     * Check if a given key exists as a factory class within this container
     *
     * @param  string  $key The key to check for
     * @return boolean      If the key exists
     */
    public function hasFactory($key)
    {
        return isset($this->map['factory'][$key]);
    }

    /**
     * Retreieves an object for a given key
     *
     * @param  string $key  The key to lookup
     * @return object|bool  The requested object. False if not present
     */
    public function get($key)
    {
        if (! is_string($key) || empty($key)) {
            throw new Exceptions\ContainerException('$key must be a string');
        }

        // if this class has already been initialised
        if (isset($this->store[$key])) {
            return $this->store[$key];
        }

        // check to see if the class has been registered
        if ($this->hasObject($key)) {
            $this->store[$key] = $this->map['persistent'][$key]($this);
            return $this->store[$key];
        }

        // if the key is registered as a factory, return a new instance
        if ($this->hasFactory($key)) {
            return $this->map['factory'][$key]($this);
        }

        // the key was not found
        throw new Exceptions\NotFoundException("The key [$key] could not be found");
    }

    public function resolve($class, $args = [])
    {
        $reflectionClass = new \ReflectionClass($class);

        // get the constructor method of the current class
        $constructor = $reflectionClass->getConstructor();

        // if there is no constructor, just return new instance
        if ($constructor === null) {
            return new $class;
        }

        // get constructor params
        $params = $constructor->getParameters();

        // If there is a constructor, but no params
        if (count($params) === 0) {
            return new $class;
        }

        foreach ($params as $param) {
            // if the param is not a class, check $args for the value
            if (is_null($param->getClass())) {
                if (isset($args[$param->name])) {
                    $newInstanceParams[] = $args[$param->name];
                }
                continue;
            }

            // if the class has been mapped to a DI instance
            if (isset($this->resolutionMap[$param->getClass()->getName()])) {
                $newInstanceParams[] = $this->get(
                    $this->resolutionMap[$param->getClass()->getName()]
                );
                continue;
            }

            // else the param is a class, so run $this->resolve on it
            $newInstanceParams[] = $this->resolve(
                $param->getClass()->getName(),
                $args
            );
        }

        // reset the resolution map created via $this->using() for next resolution
        $this->resolutionMap = [];

        // return the resolved class
        return $reflectionClass->newInstanceArgs(
            $newInstanceParams
        );
    }

    /**
     * When using resolve, this method indicates a dependency should come from this DI container,
     * instead of a new instance
     *
     * Example:
     *      ->using(['Jaws\Foo' => 'bar'])->resolve('FooBar')
     *      The resolved FooBar object will use {DI Container}->get('bar') to resolve any instances of Jaws\Foo,
     *      but any other dependency classes will be passed as new instances
     *
     * @param  string $namespaceMap An array of {global namespace of class} => {DI key to fetch}
     * @return self
     */
    public function using($namespaceMap)
    {
        $this->resolutionMap = $namespaceMap;
        return $this;
    }

    /**
     * Unsets a given key, and removes any objects associated with it from the container
     *
     * @param  string $key The key to remove
     * @return bool        Whether the key and associated object were removed
     */
    public function remove($key)
    {
        // if the key exists as a factory
        if ($this->hasFactory($key)) {
            unset($this->map['factory'][$key]);
            return ! $this->hasFactory($key);
        }

        // if the key exists as an object
        if ($this->hasObject($key)) {
            unset($this->map['persistent'][$key], $this->store[$key]);
            return ! $this->hasObject($key);
        }

        // the key did not exist
        return false;
    }

    /**
     * Sets the value at specified offset
     *
     * @param  string  $offset The key to set
     * @param  closure $value  The value to set
     */
    public function offsetSet($offset, $value)
    {
        $this->add($offset, $value);
    }

    /**
     * Checks if a given offset exists
     *
     * @param  string  $offset The key to set
     * @return  bool
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * Gets the value at specified offset.
     *
     * @param  string  $offset The key to get
     * @return  object|closure  $value  The object instance or closure if a factory class
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Unsets the value at specified offset.
     *
     * @param  string  $offset The key to set
     * @return  bool
     */
    public function offsetUnset($offset)
    {
        return $this->remove($offset);
    }
}
