<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Forms\Services\FormsUpdateRouter;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ran\PluginLib\Forms\Services\FormsUpdateRouter
 */
final class FormsUpdateRouterTest extends TestCase {
	public function test_route_calls_registered_handler_when_type_matches(): void {
		$router = new FormsUpdateRouter();
		$calls  = array();

		$handlers = array(
			'section' => function (array $data) use (&$calls): void {
				$calls[] = array('handler' => 'section', 'data' => $data);
			},
		);

		$fallback = function (string $type, array $data) use (&$calls): void {
			$calls[] = array('fallback' => $type, 'data' => $data);
		};

		$router->route('section', array('x' => 1), $handlers, $fallback);

		self::assertSame(
			array(
				array('handler' => 'section', 'data' => array('x' => 1)),
			),
			$calls
		);
	}

	public function test_route_calls_fallback_when_type_is_unknown(): void {
		$router = new FormsUpdateRouter();
		$calls  = array();

		$handlers = array(
			'section' => function (array $data) use (&$calls): void {
				$calls[] = array('handler' => 'section', 'data' => $data);
			},
		);

		$fallback = function (string $type, array $data) use (&$calls): void {
			$calls[] = array('fallback' => $type, 'data' => $data);
		};

		$router->route('unknown_type', array('y' => 2), $handlers, $fallback);

		self::assertSame(
			array(
				array('fallback' => 'unknown_type', 'data' => array('y' => 2)),
			),
			$calls
		);
	}

	public function test_create_update_function_returns_callable_that_routes(): void {
		$router = new FormsUpdateRouter();
		$calls  = array();

		$handlers = array(
			'field' => function (array $data) use (&$calls): void {
				$calls[] = array('handler' => 'field', 'data' => $data);
			},
		);

		$fallback = function (string $type, array $data) use (&$calls): void {
			$calls[] = array('fallback' => $type, 'data' => $data);
		};

		$fn = $router->create_update_function($handlers, $fallback);
		$fn('field', array('id' => 'f1'));

		self::assertSame(
			array(
				array('handler' => 'field', 'data' => array('id' => 'f1')),
			),
			$calls
		);
	}
}
