# Hodl DI

[![Travis](https://img.shields.io/travis/jakewhiteley/hodl.svg?style=for-the-badge)](https://travis-ci.org/jakewhiteley/hodl) 
[![Packagist](https://img.shields.io/packagist/v/jakewhiteley/hodl.svg?style=for-the-badge)](https://packagist.org/packages/jakewhiteley/hodl) 
[![PHP from Packagist](https://img.shields.io/packagist/php-v/jakewhiteley/hodl.svg?style=for-the-badge)](https://packagist.org/packages/jakewhiteley/hodl)



Hodl provides full autowiring capabilities for Inversion of Control, but is simple enough to use as a standalone service container without any faff.

It's a simple `ArrayAccess` Service container which takes inspiration from both Laravel and Pimple (Symfony), but sits comfortably in the middle.

## Basic Usage

A service can be added to the container by providing the class name, and a definition for the service.

```` php
$hodl->add('Some\Namespace\Foo', function() {
	return new Foo();
});
````

You should always register a service using it's full class name. This is so that the autowiring can work and classes can have their dependencies injected with no fuss.

## Retrieving a service

As simple as it gets:

```` php
$foo = $hodl->get('Some\Namespace\Foo');
````

## Checking if a service exists

As all services are referenced by the key you defined it with, you can use `has()` to check if that key has been defined previously:

```` php
use Namespace\Foo;

// using the ::class shorthand
$hodl->add(Foo::class, function() {
    return new Foo();
});

$hodl->has(Foo::class); // true
$hodl->has('some\other\class'); // false
````

## Removing services

As the Container implements ArrayAccess you can use `unset()` or the `remove()` method to remove a class:
```` php
$hodl['foo'] = function(){
    return new Foo();
};

$hodl->has('foo'); // true

$hodl->remove('foo');

$hodl->has('foo'); // false
````

## ArrayAccess style

As Hodl implements `ArrayAccess`, you can achieve the above like this instead:

````php
// add
$hodl['Some\Namespace\Foo'] = function(){
    return new Foo();
};

// get
$foo = $hodl['Some\Namespace\Foo'];

// check
if (isset($hodl['Some\Namespace\Foo')) // ...

// remove
unset($hodl['Some\Namespace\Foo']);
````

### Service definitions

When adding a new service definition, the `callable` which returns the class is passed an instance of `Hodl`, which can be used for passing arguments derived from services already within the container. **Note** You should not pass services directly into the constructor of your service. For that we have the magic of [autowiring](#autowiring-resolving-dependencies).

```` php
$hodl->get('Baz', function($hodl) {
	return new Baz($hodl->get('Some\Namespace\Foo')->someProp);
});
````

## Factories

The above examplse will return the same instance of the service no matter when it is fetched.
You can also specify that a new instance should be returned each time by using the `addFactory()` method:

```` php
$hodl->addFactory(Bar::class, function() {
	return new Bar();
});
````

## Instances
You can also add a specific instance as a service. As this is already booted, `Hodl` can derrive the class name pretty easily so there is no need to supply that.

````php
$instance = new Foo\Bar();
$instance->prop = 'foobar';

$hodl->addInstance($instance);

// ...

$hodl->get('Foo\Bar')->prop // equals 'foobar'
````

## Autowiring (resolving dependencies)

Aside as using it as a container for passing objects around, it can also be used to auotmatically resolve objects using the Reflection API and achieve Inversion of Control.

Consider the following object:

```` php
namespace Foo;

class Foo
{
    function __construct( Bar $bar )
    {
       	$this->bar = $bar;
    }
}
````

When this object is created, it needs to be passed an instance of `Foo\Bar` as the first argument.

Using the `resolve()` method this is super easy:

```` php
$foo = $hodl->resolve('Foo\Foo');
````

The object will be created an an instance of `Foo\Bar` will be initialized and passed through automatically. 

This works recursively so any dependencies of `Foo\Bar` will be magically resolved as well.

## Passing arguments

The resolve method also accepts a second argument, which is an array of extra parameters you want to pass to the object constructors.

The keys of this array must be the variable name of the parameter.

```` php
// using the Foo class in the previous example, but with a following constructor:
// function __construct( Bar $bar, int $someInt, string $someString = 'string' ) { ...

// by not passing someString as a key, the default of 'string' will be used
$foo = $hodl->resolve('Foo\Foo', [
	'someInt' => 42
]);
````

## Resolving using services

The above examples have an empty container, so all services are injected as new generic instances of that class. But if a service exists within the container, that service will be used instead - allowing your specific instance or a persistent object to be passed to any object which needs it.

````php
class Foo
{
	public $var = 'foo';
}

class Bar
{
	public $foo;
	
	public function __construct(Foo $foo)
	{
		$this->foo = $foo;
	}
}

// Add Foo as a service
$hodl->add('Foo', function() {
	return new Foo();
});

$hodl['Foo']->var = 'changed!';


$var = $hodl->resolve('Bar')->foo->var; // equals 'changed!'
````

Don't be afraid to use `resolve()` when adding a service definition to the container either!

```` php
$hodl->add('Bar', function($hodl) {
	return $hodl->resolve('Bar'); // All of Bar's dependencies will be injected as soon as it is fetched
});

````
