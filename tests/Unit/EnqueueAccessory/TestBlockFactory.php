<?php
/**
 * TestBlockFactory - Test helper class for BlockFactory
 *
 * This class extends BlockFactory to expose protected properties for testing.
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\BlockFactory;
use Ran\PluginLib\EnqueueAccessory\BlockRegistrar;

/**
 * Class TestBlockFactory
 *
 * Test helper class that extends BlockFactory to expose protected properties for testing.
 */
class TestBlockFactory extends BlockFactory {
	/**
	 * Set the registrar instance for testing
	 *
	 * @param BlockRegistrar $registrar The registrar instance to set
	 * @return void
	 */
	public function setRegistrar(BlockRegistrar $registrar): void {
		// Need to use parent class for reflection since the property is defined there
		$reflection = new \ReflectionClass(BlockFactory::class);
		$property   = $reflection->getProperty('registrar');
		$property->setAccessible(true);
		$property->setValue($this, $registrar);
	}

	/**
	 * Get the registrar instance for testing
	 *
	 * @return BlockRegistrar The registrar instance
	 */
	public function getRegistrar(): BlockRegistrar {
		// Need to use parent class for reflection since the property is defined there
		$reflection = new \ReflectionClass(BlockFactory::class);
		$property   = $reflection->getProperty('registrar');
		$property->setAccessible(true);
		return $property->getValue($this);
	}
}
