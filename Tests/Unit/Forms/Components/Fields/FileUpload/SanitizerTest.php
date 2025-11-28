<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\FileUpload;

use Ran\PluginLib\Forms\Components\Fields\FileUpload\Sanitizer;
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
		WP_Mock::userFunction('esc_url_raw')->andReturnUsing(static fn($value) => trim((string) $value))->byDefault();
		WP_Mock::userFunction('sanitize_file_name')->andReturnUsing(static function($value) {
			$value = strtolower(trim((string) $value));
			$value = preg_replace('/[^a-z0-9\._-]+/', '-', $value) ?? $value;
			return trim($value, '-');
		})->byDefault();
	}

	public function test_single_file_with_allowed_extension_is_sanitized(): void {
		$context = array('allowed_extensions' => array('jpg'));

		$result = $this->sanitizer->sanitize(' My Photo.JPG ', $context, $this->emitNotice);

		self::assertSame('my-photo.jpg', $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_disallowed_extension_is_removed_with_notice(): void {
		$context = array('allowed_extensions' => array('jpg'));

		$result = $this->sanitizer->sanitize('document.pdf', $context, $this->emitNotice);

		self::assertSame('', $result);
		self::assertSame(array('File with disallowed extension was removed.'), $this->notices);
	}

	public function test_multiple_files_filter_invalid_entries(): void {
		$context = array(
			'multiple'           => true,
			'allowed_extensions' => array('jpg'),
		);

		$result = $this->sanitizer->sanitize(array('https://example.com/image.jpg?size=large', 'note.txt'), $context, $this->emitNotice);

		self::assertSame(array('https://example.com/image.jpg?size=large'), $result);
		self::assertSame(array('File with disallowed extension was removed.'), $this->notices);
	}

	public function test_empty_input_respects_multiple_flag(): void {
		self::assertSame('', $this->sanitizer->sanitize('', array(), $this->emitNotice));
		self::assertSame(array(), $this->sanitizer->sanitize('', array('multiple' => true), $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}
}
