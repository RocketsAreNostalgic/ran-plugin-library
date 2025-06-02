<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Singleton;

use Ran\PluginLib\Singleton\SingletonAbstract;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Exception;
use ReflectionProperty;

// Dummy classes for testing SingletonAbstract
class DummySingletonA extends SingletonAbstract {
}
class DummySingletonB extends SingletonAbstract {
}

/**
 * @coversDefaultClass \Ran\PluginLib\Singleton\SingletonAbstract
 */
class SingletonAbstractTest extends PluginLibTestCase {
	public function tearDown(): void {
		// Reset the static instances property in SingletonAbstract to ensure test isolation
		$instances_property = new ReflectionProperty(SingletonAbstract::class, 'instances');
		$instances_property->setAccessible(true);
		$instances_property->setValue(null, array());
		parent::tearDown();
	}

	/**
	 * @test
	 * @covers ::get_instance
	 */
	public function get_instance_returns_same_instance_for_same_class(): void {
		$instance1 = DummySingletonA::get_instance();
		$instance2 = DummySingletonA::get_instance();

		$this->assertInstanceOf(DummySingletonA::class, $instance1);
		$this->assertSame($instance1, $instance2, 'get_instance() should return the same instance for the same class.');
	}

	/**
	 * @test
	 * @covers ::get_instance
	 */
	public function get_instance_returns_different_instances_for_different_subclasses(): void {
		$instanceA = DummySingletonA::get_instance();
		$instanceB = DummySingletonB::get_instance();

		$this->assertInstanceOf(DummySingletonA::class, $instanceA);
		$this->assertInstanceOf(DummySingletonB::class, $instanceB);
		$this->assertNotSame($instanceA, $instanceB, 'get_instance() should return different instances for different subclasses.');
	}

	/**
	 * @test
	 * @covers ::__clone
	 * @uses \Ran\PluginLib\Singleton\SingletonAbstract::get_instance
	 */
	public function clone_is_protected_and_prevents_cloning(): void {
		$instance     = DummySingletonA::get_instance();
		$reflection   = new \ReflectionClass($instance);
		$clone_method = $reflection->getMethod('__clone');

		$this->assertTrue($clone_method->isProtected(), '__clone method should be protected.');
        
		// Attempting to clone directly would cause a fatal error.
		// Verifying it's protected is sufficient to show it cannot be cloned from outside.
	}

	/**
	 * @test
	 * @covers ::__wakeup
	 * @uses \Ran\PluginLib\Singleton\SingletonAbstract::get_instance
	 */
	public function wakeup_throws_exception(): void {
		$instance            = DummySingletonA::get_instance();
		$serialized_instance = serialize($instance);

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('RanPluginLib: Cannot unserialize a singleton.');

		unserialize($serialized_instance);
	}
}
