<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\RadioOption;

use Ran\PluginLib\Forms\Components\Fields\RadioOption\Sanitizer;
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

	public function test_matching_value_returns_option_value(): void {
		$context = array('value' => ' choice ');

		self::assertSame('choice', $this->sanitizer->sanitize('choice', $context, $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}

	public function test_non_matching_value_returns_empty_string(): void {
		$context = array('value' => 'alpha');

		self::assertSame('', $this->sanitizer->sanitize('beta', $context, $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}

	public function test_null_or_empty_input_returns_empty_string(): void {
		self::assertNull($this->sanitizer->sanitize(null, array('value' => 'foo'), $this->emitNotice));
		self::assertSame('', $this->sanitizer->sanitize('', array('value' => 'foo'), $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}

	public function test_non_scalar_input_returns_empty_string(): void {
		self::assertSame('', $this->sanitizer->sanitize(array('foo'), array('value' => 'foo'), $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}
}
