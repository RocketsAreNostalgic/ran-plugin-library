<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\Textarea;

use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Components\Fields\Textarea\Sanitizer;

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

		WP_Mock::userFunction('sanitize_textarea_field')
			->andReturnUsing(static function($value) {
				return trim(strip_tags((string) $value));
			})
			->byDefault();
	}

	public function test_sanitizer_preserves_newlines(): void {
		$input  = "Line 1\r\nLine 2\rLine 3";
		$result = $this->sanitizer->sanitize($input, array(), $this->emitNotice);

		self::assertSame("Line 1\nLine 2\nLine 3", $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_non_scalar_values_return_empty_string(): void {
		$result = $this->sanitizer->sanitize(array('invalid'), array(), $this->emitNotice);

		self::assertSame('', $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_html_entities_are_preserved_post_sanitization(): void {
		// WordPress sanitizer removes tags but preserves encoded entities.
		$input  = 'Less than &lt; greater than &gt;';
		$result = $this->sanitizer->sanitize($input, array(), $this->emitNotice);

		self::assertSame('Less than &lt; greater than &gt;', $result);
		self::assertSame(array(), $this->notices);
	}
}
