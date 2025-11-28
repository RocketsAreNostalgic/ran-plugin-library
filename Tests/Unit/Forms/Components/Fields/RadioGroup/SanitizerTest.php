<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\RadioGroup;

use Ran\PluginLib\Forms\Components\Fields\RadioGroup\Sanitizer;
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

	public function test_valid_option_passes_through(): void {
		$context = array(
			'options' => array(
				array('value' => 'alpha'),
				array('value' => 'beta'),
			),
		);

		$result = $this->sanitizer->sanitize('alpha', $context, $this->emitNotice);

		self::assertSame('alpha', $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_invalid_option_triggers_notice_and_returns_empty_string(): void {
		$context = array(
			'options' => array(
				array('value' => 'alpha'),
			),
		);

		$result = $this->sanitizer->sanitize('gamma', $context, $this->emitNotice);

		self::assertSame('', $result);
		self::assertSame(array('Invalid selection was cleared.'), $this->notices);
	}

	public function test_null_value_returns_null_without_notice(): void {
		self::assertNull($this->sanitizer->sanitize(null, array(), $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}

	public function test_array_input_is_rejected_with_notice(): void {
		$result = $this->sanitizer->sanitize(array('alpha', 'beta'), array(), $this->emitNotice);

		self::assertSame('', $result);
		self::assertSame(array('Multiple selections not allowed.'), $this->notices);
	}

	public function test_empty_string_returns_empty_without_notice(): void {
		self::assertSame('', $this->sanitizer->sanitize('', array(), $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}
}
