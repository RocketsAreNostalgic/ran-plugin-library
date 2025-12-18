<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\MediaPicker;

use Ran\PluginLib\Forms\Components\Fields\MediaPicker\Validator;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use WP_Mock;

final class ValidatorTest extends PluginLibTestCase {
	private Validator $validator;

	/** @var array<int,string> */
	private array $warnings;

	/** @var callable(string):void */
	private $emitWarning;

	public function setUp(): void {
		parent::setUp();

		$this->warnings    = array();
		$this->validator   = new Validator($this->logger_mock);
		$this->emitWarning = function(string $message): void {
			$this->warnings[] = $message;
		};

		$this->registerWordPressStubs();
	}

	private function registerWordPressStubs(): void {
		WP_Mock::userFunction('__')->andReturnArg(0)->byDefault();
		WP_Mock::userFunction('_x')->andReturnArg(0)->byDefault();
		WP_Mock::userFunction('apply_filters')->andReturnArg(1)->byDefault();
	}

	public function test_empty_value_is_invalid_when_required(): void {
		$result = $this->validator->validate('', array('required' => true), $this->emitWarning);

		self::assertFalse($result);
		self::assertSame(array('Media selection is required.'), $this->warnings);
	}

	public function test_empty_value_is_valid_when_not_required(): void {
		$result = $this->validator->validate('', array('required' => false), $this->emitWarning);

		self::assertTrue($result);
		self::assertSame(array(), $this->warnings);
	}
}
