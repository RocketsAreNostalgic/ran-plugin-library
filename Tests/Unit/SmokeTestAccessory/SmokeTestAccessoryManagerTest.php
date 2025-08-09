<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\SmokeTestAccessory;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\AccessoryAPI\AccessoryBaseInterface;
use Ran\PluginLib\SmokeTestAccessory\SmokeTestAccessory;
use Ran\PluginLib\SmokeTestAccessory\SmokeTestAccessoryManager;

final class SmokeTestAccessoryManagerTest extends PluginLibTestCase {
	public function test_init_with_smoke_test_accessory_outputs_lines_and_calls_wp_die(): void {
		// Arrange: a simple provider implementing the SmokeTest interface
		$provider = new class implements SmokeTestAccessory {
			public function test(): array {
				return array('First', 'Second');
			}
		};

		// Mock wp_kses_post to return input as-is; expect wp_die to be called once
		WP_Mock::userFunction('wp_kses_post')->andReturnUsing(function ($s) {
			return $s;
		});
		WP_Mock::userFunction('wp_die')->once()->andReturnNull();

		$manager = new SmokeTestAccessoryManager();

		// Capture output
		ob_start();
		$manager->init($provider);
		$output = ob_get_clean();

		// Assert
		$this->assertIsString($output);
		$this->assertStringContainsString('<pre>', $output);
		$this->assertStringContainsString('First<br>', $output);
		$this->assertStringContainsString('Second<br>', $output);
		$this->assertStringContainsString('</pre>', $output);
	}

	public function test_init_with_non_smoke_test_accessory_does_nothing(): void {
		// Arrange: object implements AccessoryBaseInterface but not SmokeTestAccessory
		$nonSmoke = Mockery::mock(AccessoryBaseInterface::class);

		// If wp_die is called for non-smoke object, fail the test by throwing
		WP_Mock::userFunction('wp_die')->andReturnUsing(function () {
			$this->fail('wp_die() should not be called for non-smoke accessory');
		});

		$manager = new SmokeTestAccessoryManager();

		// Capture output
		ob_start();
		$manager->init($nonSmoke);
		$output = ob_get_clean();

		// Assert: no output produced
		$this->assertSame('', $output);
	}
}


