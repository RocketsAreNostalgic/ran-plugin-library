<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\Input;

use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Components\Fields\Input\Sanitizer;

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
		// Translation helpers used by TranslationService via SanitizerBase::_translate().
		WP_Mock::userFunction('__')
			->andReturnArg(0)
			->byDefault();
		WP_Mock::userFunction('_x')
			->andReturnArg(0)
			->byDefault();
		WP_Mock::userFunction('apply_filters')
			->andReturnArg(1)
			->byDefault();

		// Sanitization helpers.
		WP_Mock::userFunction('sanitize_text_field')
			->andReturnUsing(static function($value) {
				$value = (string) $value;
				$value = strip_tags($value);
				return trim($value);
			})
			->byDefault();

		WP_Mock::userFunction('sanitize_email')
			->andReturnUsing(static function($value) {
				$value = trim((string) $value);
				return preg_replace('/[^a-z0-9@._+\-]/i', '', $value) ?? '';
			})
			->byDefault();

		WP_Mock::userFunction('esc_url_raw')
			->andReturnUsing(static fn($value) => trim((string) $value))
			->byDefault();
	}

	public function test_text_input_uses_wordpress_sanitizer(): void {
		$result = $this->sanitizer->sanitize(' <strong>Hello</strong> ', array('input_type' => 'text'), $this->emitNotice);

		self::assertSame('Hello', $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_email_sanitization_removes_invalid_characters(): void {
		$result = $this->sanitizer->sanitize(' user+test()@example.com ', array('input_type' => 'email'), $this->emitNotice);

		self::assertSame('user+test@example.com', $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_url_sanitization_trims_whitespace(): void {
		$result = $this->sanitizer->sanitize(' https://example.com/path ', array('input_type' => 'url'), $this->emitNotice);

		self::assertSame('https://example.com/path', $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_phone_sanitization_removes_disallowed_characters(): void {
		$result = $this->sanitizer->sanitize(' +1 (555) 123-4567 ext 89 ', array('input_type' => 'tel'), $this->emitNotice);

		self::assertSame('+1 (555) 123-4567 89', $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_number_sanitization_clamps_and_emits_notice(): void {
		$context = array(
			'input_type' => 'number',
			'min'        => 10,
			'max'        => 20,
		);

		$result = $this->sanitizer->sanitize('5', $context, $this->emitNotice);

		self::assertSame(10, $result);
		self::assertSame(array('Value was adjusted to minimum.'), $this->notices);
	}

	public function test_number_sanitization_rejects_non_numeric_values(): void {
		$result = $this->sanitizer->sanitize('abc', array('input_type' => 'number'), $this->emitNotice);

		self::assertSame('', $result);
		self::assertSame(array('Non-numeric value was cleared.'), $this->notices);
	}

	public function test_color_sanitization_expands_three_digit_hex(): void {
		$result = $this->sanitizer->sanitize('abc', array('input_type' => 'color'), $this->emitNotice);

		self::assertSame('#AABBCC', $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_color_sanitization_rejects_invalid_values(): void {
		$result = $this->sanitizer->sanitize('zzzz', array('input_type' => 'color'), $this->emitNotice);

		self::assertSame('', $result);
		self::assertSame(array(), $this->notices);
	}
}
