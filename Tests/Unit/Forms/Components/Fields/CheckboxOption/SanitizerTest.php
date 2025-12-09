<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\CheckboxOption;

use Ran\PluginLib\Forms\Components\Fields\CheckboxOption\Sanitizer;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

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
	}

	public function test_truthy_values_return_option_value(): void {
		$context = array('value' => '  foo  ');

		self::assertSame('foo', $this->sanitizer->sanitize(true, $context, $this->emitNotice));
		self::assertSame('foo', $this->sanitizer->sanitize('on', $context, $this->emitNotice));
		self::assertSame('foo', $this->sanitizer->sanitize(1, $context, $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}

	public function test_exact_option_value_passes_through(): void {
		$context = array('value' => 'OptionA');

		self::assertSame('OptionA', $this->sanitizer->sanitize('OptionA', $context, $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}

	public function test_falsy_values_return_null(): void {
		$context = array('value' => 'yes');

		self::assertNull($this->sanitizer->sanitize(false, $context, $this->emitNotice));
		self::assertNull($this->sanitizer->sanitize('', $context, $this->emitNotice));
		self::assertNull($this->sanitizer->sanitize('0', $context, $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}
}
