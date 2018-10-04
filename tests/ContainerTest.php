<?php

namespace Hodl\Tests;

use Hodl\Container;
use Hodl\Exceptions\ContainerException;
use Hodl\Exceptions\NotFoundException;
use Hodl\Exceptions\KeyExistsException;
use Hodl\Exceptions\InvalidKeyException;
use Hodl\Exceptions\ConcreteClassNotFoundException;
use Hodl\Tests\Classes\DummyClass;
use Hodl\Tests\Classes\NoConstructor;
use Hodl\Tests\Classes\NeedsResolving;
use Hodl\Tests\Classes\Resolver;
use Hodl\Tests\Classes\Contract;
use Hodl\Tests\Classes\Concrete;
use Hodl\Tests\Classes\NeedsContract;

class ContainerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     */
    public function a_container_can_be_booted_and_extends_psr11()
    {
        $hodl = new Container();
        $this->assertInstanceOf(\Psr\Container\ContainerInterface::class, $hodl);
    }

    /**
     * @test
     */
    public function get_only_accepts_strings_as_a_key()
    {
        $hodl = new Container();

        $this->expectException(ContainerException::class);

        $hodl->get(12);
    }

    /**
     * @test
     */
    public function keys_must_be_valid_namespaces()
    {
        $this->expectException(InvalidKeyException::class);

        $hodl = new Container();
        $hodl = $hodl->add('alias', function () {
            return new DummyClass('bar');
        });
    }

    /**
     * @test
     * @return Hodl\Container An instance of Container containing a DummyClass instance.
     */
    public function an_object_can_be_added_to_the_container()
    {
        $hodl = new Container();

        $hodl->addSingleton('Hodl\Tests\Classes\DummyClass', function () {
            return new DummyClass('bar');
        });

        $this->assertTrue($hodl->has('Hodl\Tests\Classes\DummyClass'));

        return $hodl;
    }

    /**
     * @test
     * @depends an_object_can_be_added_to_the_container
     * @return Hodl\Container An instance of Container containing a DummyClass instance.
     */
    public function get_returns_the_same_class_instance_every_time(Container $hodl)
    {
        $firstAttempt = $hodl->get('Hodl\Tests\Classes\DummyClass');
        $secondAttempt = $hodl->get('Hodl\Tests\Classes\DummyClass');

        $this->assertSame($firstAttempt, $secondAttempt);
        $this->assertSame($firstAttempt->foo, $secondAttempt->foo);
        $this->assertSame($firstAttempt->bar, $secondAttempt->bar);

        return $hodl;
    }

    /**
     * @test
     * @depends get_returns_the_same_class_instance_every_time
     */
    public function get_throws_NotFoundException_when_key_not_present(Container $hodl)
    {
        $hodl->remove('Hodl\Tests\Classes\DummyClass');

        $this->expectException(NotFoundException::class);

        $hodl->get('Hodl\Tests\Classes\DummyClass');
    }

    /**
     * @test
     */
    public function keys_cannot_be_overloaded()
    {
        $hodl = new Container();

        $this->expectException(KeyExistsException::class);

        $hodl->add('Hodl\Tests\Classes\DummyClass', function () {
            return new DummyClass('bar');
        });

        $hodl->add('Hodl\Tests\Classes\DummyClass', function () {
            return new DummyClass('bar');
        });
    }


     /**
     * @test
     * @return Hodl\Container An instance of Container containing a DummyClass instance.
     */
    public function an_object_can_be_added_to_the_container_as_a_factory()
    {
        $hodl = new Container();

        $hodl->add('Hodl\Tests\Classes\DummyClass', function () {
            return new DummyClass('foo');
        });

        $this->assertTrue($hodl->has('Hodl\Tests\Classes\DummyClass'));

        return $hodl;
    }

    /**
     * @test
     * @depends an_object_can_be_added_to_the_container_as_a_factory
     * @return Hodl\Container An instance of Container containing a DummyClass instance.
     */
    public function get_returns_a_different_factory_instance_every_time(Container $hodl)
    {
        $firstAttempt = $hodl->get('Hodl\Tests\Classes\DummyClass');
        $secondAttempt = $hodl->get('Hodl\Tests\Classes\DummyClass');

        $this->assertSame($firstAttempt->foo, $secondAttempt->foo);
        $this->assertNotEquals($firstAttempt->bar, $secondAttempt->bar);

        return $hodl;
    }
    
    /**
     * @test
     * @depends get_returns_a_different_factory_instance_every_time
     */
    public function a_factory_can_be_removed(Container $hodl)
    {
        $this->assertTrue($hodl->remove('Hodl\Tests\Classes\DummyClass'));
        $this->assertFalse($hodl->has('Hodl\Tests\Classes\DummyClass'));
        $this->assertFalse($hodl->remove('Hodl\Tests\Classes\DummyClass'));

        return $hodl;
    }

    /**
     * @test
     */
    public function container_impliments_array_access_correctly()
    {
        $hodl = new Container();

        $hodl['Hodl\Tests\Classes\DummyClass'] = function () {
            return new DummyClass('foo');
        };

        $this->assertTrue(isset($hodl['Hodl\Tests\Classes\DummyClass']));
        $this->assertTrue($hodl['Hodl\Tests\Classes\DummyClass'] instanceof DummyClass);
        unset($hodl['Hodl\Tests\Classes\DummyClass']);
        $this->assertFalse(isset($hodl['Hodl\Tests\Classes\DummyClass']));
    }

    /**
     * @test
     */
    public function an_object_can_be_resolved_explicitly()
    {
        $hodl = new Container();

        $resolved = $hodl->resolve(NeedsResolving::class);

        $this->assertEquals('foobar', $resolved->resolver->var);
        $this->assertEquals('nested', $resolved->resolver->nested->var);

        $doesntNeedResolving = $hodl->resolve('Hodl\Tests\Classes\DummyClass');

        $this->assertEquals('not_set', $doesntNeedResolving->foo);

        $hasNoConstructor = $hodl->resolve(NoConstructor::class);

        $this->assertInstanceOf(NoConstructor::class, $hasNoConstructor);
    }

    /**
     * @test
     */
    public function an_object_can_be_resolved_explicitly_with_params()
    {
        $hodl = new Container();

        $doesntNeedResolving = $hodl->resolve('Hodl\Tests\Classes\DummyClass', ['string' => 'has_been_set']);

        $this->assertEquals('has_been_set', $doesntNeedResolving->foo);
    }

    /**
     * @test
     */
    public function a_specific_instance_can_be_added_to_the_container()
    {
        $hodl = new Container();

        $instance = new DummyClass('specific');

        $hodl->addInstance('Hodl\Tests\Classes\DummyClass', $instance);

        $this->assertTrue($hodl->has('Hodl\Tests\Classes\DummyClass'));
        $this->assertEquals($hodl->get('Hodl\Tests\Classes\DummyClass')->foo, 'specific');

        $hodl->remove('Hodl\Tests\Classes\DummyClass');
        $this->assertFalse($hodl->has('Hodl\Tests\Classes\DummyClass'));

        $hodl->addInstance($instance);

        $this->assertTrue($hodl->has('Hodl\Tests\Classes\DummyClass'));
        $this->assertEquals($hodl->get('Hodl\Tests\Classes\DummyClass')->foo, 'specific');

        $this->expectException(ContainerException::class);

        $hodl->addInstance('key');
    }

    /**
     * @test
     */
    public function trying_to_resolve_a_nonexistent_class_throws_an_exception()
    {
        $hodl = new Container();
        $this->expectException(ContainerException::class);

        $hodl->resolve('imaginaryClass');
    }

    /**
     * @test
     */
    public function objects_in_the_container_take_precedence_when_resolving()
    {
        $hodl = new Container();

        $hodl->addSingleton('Hodl\Tests\Classes\NeedsResolving', function ($di) {
            return $di->resolve('Hodl\Tests\Classes\NeedsResolving');
        });

        // was resolved using global scope classes
        $this->assertEquals('foobar', $hodl->get('Hodl\Tests\Classes\NeedsResolving')->resolver->var);

        $hodl->remove('Hodl\Tests\Classes\NeedsResolving');

        $hodl->add('Hodl\Tests\Classes\NeedsResolving', function ($di) {
            return $di->resolve('Hodl\Tests\Classes\NeedsResolving');
        });

        $hodl->addSingleton('Hodl\Tests\Classes\Resolver', function ($di) {
            return $di->resolve('Hodl\Tests\Classes\Resolver');
        });

        $hodl->get('Hodl\Tests\Classes\Resolver')->var = 'resolved';

        // was resolved using global scope classes
        $this->assertEquals('resolved', $hodl->get('Hodl\Tests\Classes\NeedsResolving')->resolver->var);
    }

    /**
     * @test
     */
    public function a_method_can_be_resolved_explicitly()
    {
        $hodl = new Container();

        $shouldBeResolver = $hodl->resolveMethod(DummyClass::class, 'hasNoStaticParams');

        $this->assertInstanceOf(Resolver::class, $shouldBeResolver);

        // Assert that the resolution was recursive.
        $this->assertInstanceOf(\Hodl\Tests\Classes\Nested\Resolver::class, $shouldBeResolver->nested);
    } 

    /**
     * @test
     */
    public function an_exception_is_thrown_if_resolving_a_non_existant_method()
    {
        $hodl = new Container();

        $this->expectException(ContainerException::class);
        $dummy = new DummyClass();
        // Check if an exception thrown if the class method exist.
        $hodl->resolveMethod($dummy, 'DoesntExist');        
    }

    /**
     * @test
     */
    public function an_exception_is_thrown_if_resolving_a_non_existant_method_on_non_existant_class()
    {
        $hodl = new Container();

        $this->expectException(ContainerException::class);
        // Check if an exception thrown is the class doesnt exist.
        $hodl->resolveMethod('DoesntExist', 'hasNoStaticParams');
    }

    /**
     * @test
     */
    public function a_method_can_be_resolved_for_as_existing_instances()
    {
        $hodl = new Container();

        $instance = new DummyClass();

        $shouldBeResolver = $hodl->resolveMethod($instance, 'hasNoStaticParams');

        $this->assertInstanceOf(Resolver::class, $shouldBeResolver);
        $this->assertInstanceOf(\Hodl\Tests\Classes\Nested\Resolver::class, $shouldBeResolver->nested);

        $shouldBeResolver = $hodl->resolveMethod($instance, 'isStatic');

        $this->assertInstanceOf(Resolver::class, $shouldBeResolver);
        $this->assertInstanceOf(\Hodl\Tests\Classes\Nested\Resolver::class, $shouldBeResolver->nested);
    }

    /**
     * @test
     */
    public function a_method_can_be_resolved_with_no_args()
    {
        $hodl = new Container();

        $instance = new DummyClass();

        $this->assertTrue($hodl->resolveMethod(DummyClass::class, 'hasNoParams'));

        $this->assertTrue($hodl->resolveMethod($instance, 'staticHasNoParams'));
    }

    /**
     * @test
     */
    public function objects_in_the_container_take_precedence_when_resolving_methods()
    {
        $hodl = new Container();

        $hodl->addSingleton(Resolver::class, function ($di) {
            return $di->resolve(Resolver::class);
        });

        $hodl->get(Resolver::class)->var = 'resolved';

        $shouldBeResolved = $hodl->resolveMethod(DummyClass::class, 'hasNoStaticParams');

        $this->assertEquals('resolved', $shouldBeResolved->var);
    }

    /**
     * @test
     */
    public function methods_can_be_resolved_with_args()
    {
        $hodl = new Container();

        $shouldBeResolved = $hodl->resolveMethod(DummyClass::class, 'hasParams', [
            'param' => 'not null'
        ]);

       $this->assertEquals('not null', $shouldBeResolved);
    }

    /**
     * @test
     */
    public function services_can_be_aliased()
    {
        $hodl = new Container();

        $hodl->add(DummyClass::class, function () {
            return new DummyClass('foo');
        });

        $hodl->alias(DummyClass::class, 'dummy');
        $this->assertTrue($hodl->has('dummy'));

        $this->assertTrue($hodl->get('dummy') instanceof DummyClass);

        return $hodl;
    }   

    /**
     * @test
     * @depends services_can_be_aliased
     */
    public function services_can_be_removed_by_alias($hodl)
    {
        $this->assertTrue($hodl->get('dummy') instanceof DummyClass);
        $this->assertTrue($hodl->get(DummyClass::class) instanceof DummyClass);
        $hodl->remove('dummy');
        $this->assertFalse($hodl->has('dummy'));
        $this->assertFalse($hodl->has(DummyClass::class));
    }    

    /**
     * @test
     */
    public function services_have_aliases_removed_upon_remove()
    {
        $hodl = new Container();

        $hodl->add(DummyClass::class, function () {
            return new DummyClass('foo');
        });

        $hodl->alias(DummyClass::class, 'dummy');

        $this->assertTrue($hodl->get('dummy') instanceof DummyClass);
        $this->assertTrue($hodl->get(DummyClass::class) instanceof DummyClass);
        $hodl->remove(DummyClass::class);
        $this->assertFalse($hodl->has('dummy'));
        $this->assertFalse($hodl->has(DummyClass::class));
    }

    /**
     * @test
     */
    public function singletons_can_be_aliased()
    {
        $hodl = new Container();

        $hodl->addSingleton(DummyClass::class, function () {
            return new DummyClass('foo');
        });

        $hodl->alias(DummyClass::class, 'dummy');
        $this->assertTrue($hodl->has('dummy'));

        $singleton = $hodl->get('dummy');
        $this->assertTrue($singleton instanceof DummyClass);
        $this->assertEquals($singleton->foo, 'foo');
        $this->assertEquals($singleton->foo, $hodl->get('dummy')->foo);
        return $hodl;
    }

    /**
     * @test
     * @depends singletons_can_be_aliased
     */
    public function singletons_can_be_removed_by_alias($hodl)
    {
        $this->assertTrue($hodl->get('dummy') instanceof DummyClass);
        $this->assertTrue($hodl->get(DummyClass::class) instanceof DummyClass);
        $hodl->remove('dummy');
        $this->assertFalse($hodl->has('dummy'));
        $this->assertFalse($hodl->has(DummyClass::class));
    }

    /**
     * @test
     */
    public function singletons_have_aliases_removed_upon_remove()
    {
        $hodl = new Container();

        $hodl->addSingleton(DummyClass::class, function () {
            return new DummyClass('foo');
        });

        $hodl->alias(DummyClass::class, 'dummy');

        $this->assertTrue($hodl->get('dummy') instanceof DummyClass);
        $this->assertTrue($hodl->get(DummyClass::class) instanceof DummyClass);
        $hodl->remove(DummyClass::class);
        $this->assertFalse($hodl->has('dummy'));
        $this->assertFalse($hodl->has(DummyClass::class));
    }

    /**
     * @test
     */
    public function services_can_be_bound_to_interfaces()
    {
        $hodl = new Container();

        $hodl->add(Concrete::class, function () {
            return new Concrete('foo');
        });

        $hodl->bind(Concrete::class, Contract::class);

        $this->assertTrue($hodl->get(Concrete::class) instanceof Contract);

        $resolved = $hodl->resolve(NeedsContract::class);

        $this->assertTrue($resolved->contract instanceof Contract);
    }

    /**
     * @test
     */
    public function bindings_can_be_removed_from_a_service()
    {
        $hodl = new Container();

        $hodl->add(Concrete::class, function () {
            return new Concrete('foo');
        });

        $hodl->bind(Concrete::class, Contract::class);

        $this->assertTrue($hodl->get(Contract::class) instanceof Concrete);
        $hodl->removeAlias(Contract::class);
        
        $this->assertTrue($hodl->has(Concrete::class) );

        $this->expectException(NotFoundException::class);
        $hodl->get(Contract::class);
    }

    /**
     * @test
     */
    public function removeAlias_returns_false_if_no_action_taken()
    {
        $hodl = new Container();
        $this->assertFalse( $hodl->removeAlias('doesntExist'));
    }

    /**
     * @test
     */
    public function ConcreteClassNotFoundException_is_thrown_when_resolving_an_unbound_interface()
    {
        $hodl = new Container();

        $this->expectException(ConcreteClassNotFoundException::class);
        $resolved = $hodl->resolve(NeedsContract::class);
    }

     /**
     * @test
     */
    public function instances_can_be_bound_to_interfaces()
    {
        $hodl = new Container();

        $i = new Concrete();

        $hodl->addInstance($i);

        $hodl->bind(Concrete::class, Contract::class);

        $this->assertTrue($hodl->get(Concrete::class) instanceof Contract);

        $resolved = $hodl->resolve(NeedsContract::class);

        $this->assertTrue($resolved->contract instanceof Contract);
    }
}
