<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\Select;

use Ran\PluginLib\Forms\Components\Fields\Select\Sanitizer;
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

	private function emitNotice(string $message): void {
		$this->notices[] = $message;
	}

	public function test_valid_option_passes_through_without_notice(): void {
		$context = array(
			'options' => array(
				array('value' => 'red', 'label' => 'Red'),
				array('value' => 'blue', 'label' => 'Blue'),
			),
		);

		$result = $this->sanitizer->sanitize('red', $context, $this->emitNotice);

		self::assertSame('red', $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_invalid_option_returns_empty_string_and_notice(): void {
		$context = array(
			'options' => array(
				array('value' => 'alpha', 'label' => 'Alpha'),
			),
		);

		$result = $this->sanitizer->sanitize('omega', $context, $this->emitNotice);

		self::assertSame('', $result);
		self::assertSame(array('Invalid selection was cleared.'), $this->notices);
	}

	public function test_null_or_empty_value_returns_empty_without_notice(): void {
		self::assertNull($this->sanitizer->sanitize(null, array(), $this->emitNotice));
		self::assertSame('', $this->sanitizer->sanitize('', array(), $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}

	public function test_allowed_special_characters_in_value_pass_through(): void {
		$context = array(
			'options' => array(
				array('value' => 'option:1', 'label' => 'Option 1'),
			),
		);

		$result = $this->sanitizer->sanitize('option:1', $context, $this->emitNotice);

		self::assertSame('option:1', $result);
		self::assertSame(array(), $this->notices);
	}
}
