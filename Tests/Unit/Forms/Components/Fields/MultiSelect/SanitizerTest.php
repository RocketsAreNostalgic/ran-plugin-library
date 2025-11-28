<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\MultiSelect;

use Ran\PluginLib\Forms\Components\Fields\MultiSelect\Sanitizer;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use WP_Mock;

final class SanitizerTest extends PluginLibTestCase {
	private Sanitizer $sanitizer;

	/** @var array<int,string> */
	private array $notices;

	/** @var callable(string):void */
	private $emitNotice;

	public function setUp(): void {
		parent::setUp();

		$this->notices    = array();
		$this->sanitizer  = new Sanitizer($this->logger_mock);
		$this->emitNotice = function(string $message): void {
			$this->notices[] = $message;
		};

		$this->registerWordPressStubs();
	}

	private function registerWordPressStubs(): void {
		WP_Mock::userFunction('__')->andReturnArg(0)->byDefault();
		WP_Mock::userFunction('_x')->andReturnArg(0)->byDefault();
		WP_Mock::userFunction('apply_filters')->andReturnArg(1)->byDefault();
	}

	public function test_valid_values_are_retained(): void {
		$context = array(
			'options' => array(
				array('value' => 'one'),
				array('value' => 'two'),
			),
		);

		$result = $this->sanitizer->sanitize(array('one', 'two'), $context, $this->emitNotice);

		self::assertSame(array('one', 'two'), $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_invalid_values_are_removed_with_notice(): void {
		$context = array(
			'options' => array(
				array('value' => 'alpha'),
			),
		);

		$result = $this->sanitizer->sanitize(array('alpha', 'beta'), $context, $this->emitNotice);

		self::assertSame(array('alpha'), $result);
		self::assertSame(array('Some invalid selections were removed.'), $this->notices);
	}

	public function test_empty_value_returns_empty_array_without_notice(): void {
		self::assertNull($this->sanitizer->sanitize(null, array(), $this->emitNotice));
		self::assertSame(array(), $this->sanitizer->sanitize('', array(), $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}
}
