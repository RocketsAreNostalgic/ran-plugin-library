<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\CheckboxGroup;

use Ran\PluginLib\Forms\Components\Fields\CheckboxGroup\Sanitizer;
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

	public function test_scalar_value_is_coerced_and_retained_when_allowed(): void {
		$context = array(
			'options' => array(
				array('value' => ' red '),
				array('value' => 'blue'),
			),
		);

		$result = $this->sanitizer->sanitize(' red ', $context, $this->emitNotice);

		self::assertSame(array('red'), $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_invalid_entries_are_removed_and_notice_emitted(): void {
		$context = array(
			'options' => array(
				array('value' => 'red'),
				array('value' => 'blue'),
			),
		);

		$input  = array('red', 'green');
		$result = $this->sanitizer->sanitize($input, $context, $this->emitNotice);

		self::assertSame(array('red'), $result);
		self::assertSame(array('Some invalid selections were removed.'), $this->notices);
	}

	public function test_empty_inputs_return_empty_array_without_notice(): void {
		$context = array('options' => array());

		self::assertNull($this->sanitizer->sanitize(null, $context, $this->emitNotice));
		self::assertSame(array(), $this->sanitizer->sanitize('', $context, $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}
}
