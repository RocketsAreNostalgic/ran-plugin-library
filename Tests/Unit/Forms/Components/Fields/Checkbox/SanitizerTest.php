<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\Checkbox;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Components\Fields\Checkbox\Sanitizer;

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

	public function test_returns_checked_value_when_value_matches_context(): void {
		$context = array(
			'checked_value'   => 'confirmed',
			'unchecked_value' => 'pending',
		);

		$result = $this->sanitizer->sanitize('confirmed', $context, $this->emitNotice);

		self::assertSame('confirmed', $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_truthy_inputs_coerce_to_sanitized_checked_value(): void {
		$context = array(
			'checked_value'   => '  yes  ',
			'unchecked_value' => 'no',
		);

		$resultTrue  = $this->sanitizer->sanitize(true, $context, $this->emitNotice);
		$resultOne   = $this->sanitizer->sanitize(1, $context, $this->emitNotice);
		$resultOnStr = $this->sanitizer->sanitize('on', $context, $this->emitNotice);

		self::assertSame('yes', $resultTrue);
		self::assertSame('yes', $resultOne);
		self::assertSame('yes', $resultOnStr);
		self::assertSame(array(), $this->notices);
	}

	public function test_falsy_inputs_return_sanitized_unchecked_value(): void {
		$context = array(
			'checked_value'   => 'yes',
			'unchecked_value' => '  no  ',
		);

		self::assertSame('no', $this->sanitizer->sanitize(false, $context, $this->emitNotice));
		self::assertSame('no', $this->sanitizer->sanitize('0', $context, $this->emitNotice));
		self::assertSame('no', $this->sanitizer->sanitize('', $context, $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}

	public function test_unchecked_value_defaults_to_empty_string_when_not_provided(): void {
		$truthyResult = $this->sanitizer->sanitize(true, array('checked_value' => 'on'), $this->emitNotice);

		self::assertSame('on', $truthyResult);

		$falsyResult = $this->sanitizer->sanitize(false, array('checked_value' => 'on'), $this->emitNotice);
		self::assertSame('', $falsyResult);
		self::assertSame(array(), $this->notices);
	}

	public function test_null_input_passes_through_when_allowed(): void {
		$result = $this->sanitizer->sanitize(null, array(), $this->emitNotice);

		self::assertNull($result);
		self::assertSame(array(), $this->notices);
	}
}
