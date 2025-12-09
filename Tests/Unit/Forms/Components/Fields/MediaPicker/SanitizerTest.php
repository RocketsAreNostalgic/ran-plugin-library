<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\MediaPicker;

use Ran\PluginLib\Forms\Components\Fields\MediaPicker\Sanitizer;
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

	public function test_single_id_returns_positive_integer(): void {
		$result = $this->sanitizer->sanitize('42', array(), $this->emitNotice);

		self::assertSame(42, $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_invalid_single_id_is_cleared_with_notice(): void {
		$result = $this->sanitizer->sanitize('not-a-number', array(), $this->emitNotice);

		self::assertSame('', $result);
		self::assertSame(array('Invalid media ID was cleared.'), $this->notices);
	}

	public function test_multiple_ids_filters_invalid_entries(): void {
		$context = array('multiple' => true);

		$result = $this->sanitizer->sanitize(array('4', '0', 'abc', 9), $context, $this->emitNotice);

		self::assertSame(array(4, 9), $result);
		self::assertSame(array('Some invalid media IDs were removed.'), $this->notices);
	}

	public function test_empty_value_respects_multiple_flag(): void {
		self::assertSame('', $this->sanitizer->sanitize('', array(), $this->emitNotice));
		self::assertSame(array(), $this->sanitizer->sanitize('', array('multiple' => true), $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}
}
