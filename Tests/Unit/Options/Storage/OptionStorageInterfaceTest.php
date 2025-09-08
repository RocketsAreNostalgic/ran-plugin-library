<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options\Storage;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Options\Storage\OptionStorageInterface;

final class OptionStorageInterfaceTest extends TestCase {
	/**
	 * @coversNothing
	 */
	public function test_interface_exists(): void {
		$this->assertTrue(interface_exists(OptionStorageInterface::class), 'OptionStorageInterface should exist');
	}

	/**
	 * @coversNothing
	 */
	public function test_interface_methods_signature(): void {
		$rc       = new ReflectionClass(OptionStorageInterface::class);
		$expected = array(
		    'scope',
		    'blogId',
		    'supports_autoload',
		    'read',
		    'update',
		    'add',
		    'delete',
		);
		foreach ($expected as $name) {
			$this->assertTrue($rc->hasMethod($name), "Missing method: {$name}");
		}

		$method = $rc->getMethod('update');
		$params = $method->getParameters();
		$this->assertCount(3, $params, 'update() must have 3 params: (string $key, mixed $value, bool $autoload=false)');
		$this->assertSame('key', $params[0]->getName());
		$this->assertSame('value', $params[1]->getName());
		$this->assertSame('autoload', $params[2]->getName());

		$method = $rc->getMethod('add');
		$params = $method->getParameters();
		$this->assertCount(3, $params, 'add() must have 3 params: (string $key, mixed $value, bool $autoload=false)');
		$this->assertSame('key', $params[0]->getName());
		$this->assertSame('value', $params[1]->getName());
		$this->assertSame('autoload', $params[2]->getName());

		$method = $rc->getMethod('read');
		$params = $method->getParameters();
		$this->assertCount(1, $params, 'read() must have 1 param: string $key');

		$method = $rc->getMethod('delete');
		$params = $method->getParameters();
		$this->assertCount(1, $params, 'delete() must have 1 param: string $key');
	}
}
