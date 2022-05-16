<?php

namespace Hodl\Tests;

use Hodl\Container;
use Hodl\Exceptions\ConcreteClassNotFoundException;
use Hodl\Exceptions\ContainerException;
use Hodl\Exceptions\InvalidKeyException;
use Hodl\Exceptions\KeyExistsException;
use Hodl\Exceptions\NotFoundException;
use Hodl\Tests\Classes\CanHaveConstructorParams;
use Hodl\Tests\Classes\Concrete;
use Hodl\Tests\Classes\Contract;
use Hodl\Tests\Classes\DummyClass;
use Hodl\Tests\Classes\NeedsContract;
use Hodl\Tests\Classes\NeedsResolving;
use Hodl\Tests\Classes\NeedsServiceAndConstructorParams;
use Hodl\Tests\Classes\NoConstructor;
use Hodl\Tests\Classes\PrimitivesTest;
use Hodl\Tests\Classes\Resolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ContainerTest extends TestCase
{
    /**
     * @test
     */
    public function a_container_can_be_booted_and_extends_psr11(): void
    {
        $hodl = new Container();
        $this->assertInstanceOf(ContainerInterface::class, $hodl);
    }

    /**
     * @test
     */
    public function keys_must_be_valid_namespaces(): void
    {
        $this->expectException(InvalidKeyException::class);

        $hodl = new Container();
        $hodl->add('alias', function () {
            return new DummyClass('bar');
        });
    }

    /**
     * @test
     * @return \Hodl\Container An instance of Container containing a DummyClass instance.
     */
    public function an_object_can_be_added_to_the_container(): Container
    {
        $hodl = new Container();

        $hodl->addSingleton(DummyClass::class, function () {
            return new DummyClass('bar');
        });

        $this->assertTrue($hodl->has(DummyClass::class));

        return $hodl;
    }

    /**
     * @test
     * @depends an_object_can_be_added_to_the_container
     * @return \Hodl\Container|\Hodl\Tests\Hodl\Container
     */
    public function get_returns_the_same_class_instance_every_time(Container $hodl)
    {
        $firstAttempt = $hodl->get(DummyClass::class);
        $secondAttempt = $hodl->get(DummyClass::class);

        $this->assertSame($firstAttempt, $secondAttempt);
        $this->assertSame($firstAttempt->foo, $secondAttempt->foo);
        $this->assertSame($firstAttempt->bar, $secondAttempt->bar);

        return $hodl;
    }

    /**
     * @test
     * @depends get_returns_the_same_class_instance_every_time
     */
    public function get_throws_NotFoundException_when_key_not_present(Container $hodl): void
    {
        $hodl->remove(DummyClass::class);

        $this->expectException(NotFoundException::class);

        $hodl->get(DummyClass::class);
    }

    /**
     * @test
     */
    public function keys_cannot_be_overloaded(): void
    {
        $hodl = new Container();

        $this->expectException(KeyExistsException::class);

        $hodl->add(DummyClass::class, function () {
            return new DummyClass('bar');
        });

        $hodl->add(DummyClass::class, function () {
            return new DummyClass('bar');
        });
    }

    /**
     * @test
     * @return \Hodl\Container An instance of Container containing a DummyClass instance.
     */
    public function an_object_can_be_added_to_the_container_as_a_factory(): Container
    {
        $hodl = new Container();

        $hodl->add(DummyClass::class, function () {
            return new DummyClass('foo');
        });

        $this->assertTrue($hodl->has(DummyClass::class));

        return $hodl;
    }

    /**
     * @test
     * @depends an_object_can_be_added_to_the_container_as_a_factory
     * @return \Hodl\Container An instance of Container containing a DummyClass instance.
     */
    public function get_returns_a_different_factory_instance_every_time(Container $hodl): Container
    {
        $firstAttempt = $hodl->get(DummyClass::class);
        $secondAttempt = $hodl->get(DummyClass::class);

        $this->assertSame($firstAttempt->foo, $secondAttempt->foo);
        $this->assertNotEquals($firstAttempt->bar, $secondAttempt->bar);

        return $hodl;
    }

    /**
     * @test
     * @depends get_returns_a_different_factory_instance_every_time
     */
    public function a_factory_can_be_removed(Container $hodl): Container
    {
        $this->assertTrue($hodl->remove(DummyClass::class));
        $this->assertFalse($hodl->has(DummyClass::class));
        $this->assertFalse($hodl->remove(DummyClass::class));

        return $hodl;
    }

    /**
     * @test
     */
    public function container_impliments_array_access_correctly(): void
    {
        $hodl = new Container();

        $hodl[DummyClass::class] = static function () {
            return new DummyClass('foo');
        };

        $this->assertTrue(isset($hodl[DummyClass::class]));
        $this->assertInstanceOf(DummyClass::class, $hodl[DummyClass::class]);
        unset($hodl[DummyClass::class]);
        $this->assertFalse(isset($hodl[DummyClass::class]));
    }

    /**
     * @test
     */
    public function an_object_can_be_resolved_explicitly(): void
    {
        $hodl = new Container();

        $resolved = $hodl->resolve(NeedsResolving::class);

        $this->assertEquals('foobar', $resolved->resolver->var);
        $this->assertEquals('nested', $resolved->resolver->nested->var);

        $doesntNeedResolving = $hodl->resolve(DummyClass::class);

        $this->assertEquals('not_set', $doesntNeedResolving->foo);

        $hasNoConstructor = $hodl->resolve(NoConstructor::class);

        $this->assertInstanceOf(NoConstructor::class, $hasNoConstructor);
    }

    /**
     * @test
     */
    public function an_object_can_be_resolved_explicitly_with_params(): void
    {
        $hodl = new Container();

        $doesntNeedResolving = $hodl->resolve(DummyClass::class, ['string' => 'has_been_set']);

        $this->assertEquals('has_been_set', $doesntNeedResolving->foo);
    }

    /**
     * @test
     */
    public function an_object_can_be_resolved_explicitly_with_typehinted_primitive_params(): void
    {
        $hodl = new Container();

        $doesntNeedResolving = $hodl->resolve(PrimitivesTest::class, ['string' => 'has_been_set']);

        $this->assertEquals('has_been_set', $doesntNeedResolving->foo);
    }

    /**
     * @test
     */
    public function a_specific_instance_can_be_added_to_the_container(): void
    {
        $hodl = new Container();

        $instance = new DummyClass('specific');

        $hodl->addInstance(DummyClass::class, $instance);

        $this->assertTrue($hodl->has(DummyClass::class));
        $this->assertEquals('specific', $hodl->get(DummyClass::class)->foo);

        $hodl->remove(DummyClass::class);
        $this->assertFalse($hodl->has(DummyClass::class));

        $hodl->addInstance($instance);

        $this->assertTrue($hodl->has(DummyClass::class));
        $this->assertEquals('specific', $hodl->get(DummyClass::class)->foo);

        $this->expectException(ContainerException::class);

        $hodl->addInstance('key');
    }

    /**
     * @test
     */
    public function trying_to_resolve_a_nonexistent_class_throws_an_exception(): void
    {
        $hodl = new Container();
        $this->expectException(ContainerException::class);

        $hodl->resolve('imaginaryClass');
    }

    /**
     * @test
     */
    public function objects_in_the_container_take_precedence_when_resolving(): void
    {
        $hodl = new Container();

        $hodl->addSingleton(NeedsResolving::class, function ($di) {
            return $di->resolve(NeedsResolving::class);
        });

        // was resolved using global scope classes
        $this->assertEquals('foobar', $hodl->get(NeedsResolving::class)->resolver->var);

        $hodl->remove(NeedsResolving::class);

        $hodl->add(NeedsResolving::class, function ($di) {
            return $di->resolve(NeedsResolving::class);
        });

        $hodl->addSingleton(Resolver::class, function ($di) {
            return $di->resolve(Resolver::class);
        });

        $hodl->get(Resolver::class)->var = 'resolved';

        // was resolved using global scope classes
        $this->assertEquals('resolved', $hodl->get(NeedsResolving::class)->resolver->var);
    }

    /**
     * @test
     */
    public function a_method_can_be_resolved_explicitly(): void
    {
        $hodl = new Container();
        $dummy = new DummyClass();

        $shouldBeResolver = $hodl->resolveMethod($dummy, 'hasNoStaticParams');

        $this->assertInstanceOf(Resolver::class, $shouldBeResolver);

        // Assert that the resolution was recursive.
        $this->assertInstanceOf(Classes\Nested\Resolver::class, $shouldBeResolver->nested);
    }

    /**
     * @test
     */
    public function a_static_method_can_be_resolved_explicitly(): void
    {
        $hodl = new Container();
        $shouldBeResolver = $hodl->resolveMethod(DummyClass::class, 'isStatic');

        $this->assertInstanceOf(Resolver::class, $shouldBeResolver);

        // Assert that the resolution was recursive.
        $this->assertInstanceOf(Classes\Nested\Resolver::class, $shouldBeResolver->nested);
    }

    /**
     * @test
     */
    public function an_exception_is_thrown_if_resolving_a_non_existant_method(): void
    {
        $hodl = new Container();

        $this->expectException(ContainerException::class);
        $dummy = new DummyClass();
        // Check if an exception thrown if the class method exist.
        $hodl->resolveMethod($dummy, 'DoesntExist');
    }

    /**
     * @test
     * @requires PHP >= 8.0
     */
    public function an_exception_is_thrown_if_resolving_a_non_static_method_statically(): void
    {
        $this->expectException(ContainerException::class);

        $hodl = new Container();
        $shouldBeResolver = $hodl->resolveMethod(DummyClass::class, 'hasNoStaticParams');
    }

    /**
     * @test
     */
    public function an_exception_is_thrown_if_resolving_a_non_existant_method_on_non_existant_class(): void
    {
        $hodl = new Container();

        $this->expectException(ContainerException::class);
        // Check if an exception thrown is the class doesnt exist.
        $hodl->resolveMethod('DoesntExist', 'hasNoStaticParams');
    }

    /**
     * @test
     */
    public function a_method_can_be_resolved_for_as_existing_instances(): void
    {
        $hodl = new Container();

        $instance = new DummyClass();

        $shouldBeResolver = $hodl->resolveMethod($instance, 'hasNoStaticParams');

        $this->assertInstanceOf(Resolver::class, $shouldBeResolver);
        $this->assertInstanceOf(Classes\Nested\Resolver::class, $shouldBeResolver->nested);

        $shouldBeResolver = $hodl->resolveMethod($instance, 'isStatic');

        $this->assertInstanceOf(Resolver::class, $shouldBeResolver);
        $this->assertInstanceOf(Classes\Nested\Resolver::class, $shouldBeResolver->nested);
    }

    /**
     * @test
     */
    public function a_method_can_be_resolved_with_no_args(): void
    {
        $hodl = new Container();

        $instance = new DummyClass();

        $this->assertTrue($hodl->resolveMethod($instance, 'hasNoParams'));

        $this->assertTrue($hodl->resolveMethod(DummyClass::class, 'staticHasNoParams'));
    }

    /**
     * @test
     */
    public function objects_in_the_container_take_precedence_when_resolving_methods(): void
    {
        $hodl = new Container();
        $instance = new DummyClass();

        $hodl->addSingleton(Resolver::class, function ($di) {
            return $di->resolve(Resolver::class);
        });

        $hodl->get(Resolver::class)->var = 'resolved';

        $shouldBeResolved = $hodl->resolveMethod($instance, 'hasNoStaticParams');

        $this->assertEquals('resolved', $shouldBeResolved->var);
    }

    /**
     * @test
     */
    public function methods_can_be_resolved_with_args(): void
    {
        $hodl = new Container();
        $instance = new DummyClass();

        $shouldBeResolved = $hodl->resolveMethod($instance, 'hasParams', [
            'param' => 'not null',
        ]);

        $this->assertEquals('not null', $shouldBeResolved);
    }

    /**
     * @test
     */
    public function services_can_be_aliased(): Container
    {
        $hodl = new Container();

        $hodl->add(DummyClass::class, function () {
            return new DummyClass('foo');
        });

        $hodl->alias(DummyClass::class, 'dummy');
        $this->assertTrue($hodl->has('dummy'));

        $this->assertInstanceOf(DummyClass::class, $hodl->get('dummy'));

        return $hodl;
    }

    /**
     * @test
     * @depends services_can_be_aliased
     */
    public function services_can_be_removed_by_alias($hodl): void
    {
        $this->assertInstanceOf(DummyClass::class, $hodl->get('dummy'));
        $this->assertInstanceOf(DummyClass::class, $hodl->get(DummyClass::class));
        $hodl->remove('dummy');
        $this->assertFalse($hodl->has('dummy'));
        $this->assertFalse($hodl->has(DummyClass::class));
    }

    /**
     * @test
     */
    public function services_have_aliases_removed_upon_remove(): void
    {
        $hodl = new Container();

        $hodl->add(DummyClass::class, function () {
            return new DummyClass('foo');
        });

        $hodl->alias(DummyClass::class, 'dummy');

        $this->assertInstanceOf(DummyClass::class, $hodl->get('dummy'));
        $this->assertInstanceOf(DummyClass::class, $hodl->get(DummyClass::class));
        $hodl->remove(DummyClass::class);
        $this->assertFalse($hodl->has('dummy'));
        $this->assertFalse($hodl->has(DummyClass::class));
    }

    /**
     * @test
     */
    public function singletons_can_be_aliased(): Container
    {
        $hodl = new Container();

        $hodl->addSingleton(DummyClass::class, function () {
            return new DummyClass('foo');
        });

        $hodl->alias(DummyClass::class, 'dummy');
        $this->assertTrue($hodl->has('dummy'));

        $singleton = $hodl->get('dummy');
        $this->assertInstanceOf(DummyClass::class, $singleton);
        $this->assertEquals('foo', $singleton->foo);
        $this->assertEquals($singleton->foo, $hodl->get('dummy')->foo);
        return $hodl;
    }

    /**
     * @test
     * @depends singletons_can_be_aliased
     */
    public function singletons_can_be_removed_by_alias($hodl): void
    {
        $this->assertInstanceOf(DummyClass::class, $hodl->get('dummy'));
        $this->assertInstanceOf(DummyClass::class, $hodl->get(DummyClass::class));
        $hodl->remove('dummy');
        $this->assertFalse($hodl->has('dummy'));
        $this->assertFalse($hodl->has(DummyClass::class));
    }

    /**
     * @test
     */
    public function singletons_have_aliases_removed_upon_remove(): void
    {
        $hodl = new Container();

        $hodl->addSingleton(DummyClass::class, function () {
            return new DummyClass('foo');
        });

        $hodl->alias(DummyClass::class, 'dummy');

        $this->assertInstanceOf(DummyClass::class, $hodl->get('dummy'));
        $this->assertInstanceOf(DummyClass::class, $hodl->get(DummyClass::class));
        $hodl->remove(DummyClass::class);
        $this->assertFalse($hodl->has('dummy'));
        $this->assertFalse($hodl->has(DummyClass::class));
    }

    /**
     * @test
     */
    public function services_can_be_bound_to_interfaces(): void
    {
        $hodl = new Container();

        $hodl->add(Concrete::class, function () {
            return new Concrete('foo');
        });

        $hodl->bind(Concrete::class, Contract::class);

        $this->assertInstanceOf(Contract::class, $hodl->get(Concrete::class));

        $resolved = $hodl->resolve(NeedsContract::class);

        $this->assertInstanceOf(Contract::class, $resolved->contract);
    }

    /**
     * @test
     */
    public function bindings_can_be_removed_from_a_service(): void
    {
        $hodl = new Container();

        $hodl->add(Concrete::class, function () {
            return new Concrete('foo');
        });

        $hodl->bind(Concrete::class, Contract::class);

        $this->assertInstanceOf(Concrete::class, $hodl->get(Contract::class));
        $hodl->removeAlias(Contract::class);

        $this->assertTrue($hodl->has(Concrete::class));

        $this->expectException(NotFoundException::class);
        $hodl->get(Contract::class);
    }

    /**
     * @test
     */
    public function removeAlias_returns_false_if_no_action_taken(): void
    {
        $hodl = new Container();
        $this->assertFalse($hodl->removeAlias('doesntExist'));
    }

    /**
     * @test
     */
    public function ConcreteClassNotFoundException_is_thrown_when_resolving_an_unbound_interface(): void
    {
        $hodl = new Container();

        $this->expectException(ConcreteClassNotFoundException::class);
        $resolved = $hodl->resolve(NeedsContract::class);
    }

    /**
     * @test
     */
    public function instances_can_be_bound_to_interfaces(): void
    {
        $hodl = new Container();

        $i = new Concrete();

        $hodl->addInstance($i);

        $hodl->bind(Concrete::class, Contract::class);

        $this->assertInstanceOf(Contract::class, $hodl->get(Concrete::class));

        $resolved = $hodl->resolve(NeedsContract::class);

        $this->assertInstanceOf(Contract::class, $resolved->contract);
    }

    /**
     * @test
     */
    public function instances_can_be_made_with_parameters(): void
    {
        $hodl = new Container();

        $hodl->add(CanHaveConstructorParams::class, function (Container $hodl, $foo) {
            return new CanHaveConstructorParams($foo);
        });

        $object = $hodl->get(CanHaveConstructorParams::class, 'bar');
        $this->assertEquals('bar', $object->getFoo());
    }

    /**
     * @test
     */
    public function instances_can_be_resolved_with_parameters(): void
    {
        $hodl = new Container();

        $hodl->add(CanHaveConstructorParams::class, function (Container $hodl, $foo) {
            return $hodl->resolve(CanHaveConstructorParams::class, \compact('foo'));
        });

        $object = $hodl->get(CanHaveConstructorParams::class, 'chaz');
        $this->assertEquals('chaz', $object->getFoo());
    }

    /**
     * @test
     */
    public function instances_can_be_resolved_with_parameters_and_services(): void
    {
        $hodl = new Container();

        $hodl->add(NeedsServiceAndConstructorParams::class, function (Container $hodl, $foo) {
            return $hodl->resolve(NeedsServiceAndConstructorParams::class, \compact('foo'));
        });

        $object = $hodl->get(NeedsServiceAndConstructorParams::class, 'chaz');

        $this->assertEquals('chaz', $object->getFoo());
        $this->assertInstanceOf(Resolver::class, $object->resolver);
    }

    /**
     * @test
     */
    public function singletons_can_be_made_with_parameters(): void
    {
        $hodl = new Container();

        $hodl->addSingleton(CanHaveConstructorParams::class, function (Container $hodl, $foo) {
            return new CanHaveConstructorParams($foo);
        });

        $object = $hodl->get(CanHaveConstructorParams::class, 'bar');
        $this->assertEquals('bar', $object->getFoo());
    }

    /**
     * @test
     */
    public function singletons_can_be_resolved_with_parameters(): void
    {
        $hodl = new Container();

        $hodl->addSingleton(CanHaveConstructorParams::class, function (Container $hodl, $foo) {
            return $hodl->resolve(CanHaveConstructorParams::class, \compact('foo'));
        });

        $object = $hodl->get(CanHaveConstructorParams::class, 'chaz');
        $this->assertEquals('chaz', $object->getFoo());
    }

    /**
     * @test
     */
    public function singletons_can_be_resolved_with_parameters_and_services(): void
    {
        $hodl = new Container();

        $hodl->addSingleton(NeedsServiceAndConstructorParams::class, function (Container $hodl, $foo) {
            return $hodl->resolve(NeedsServiceAndConstructorParams::class, \compact('foo'));
        });

        $object = $hodl->get(NeedsServiceAndConstructorParams::class, 'chaz');

        $this->assertEquals('chaz', $object->getFoo());
        $this->assertInstanceOf(Resolver::class, $object->resolver);
    }
}
