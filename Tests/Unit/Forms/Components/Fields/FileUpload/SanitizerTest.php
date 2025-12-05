<?php
/**
 * Tests for FileUpload Sanitizer.
 *
 * The Sanitizer now handles actual file uploads from $_FILES and creates
 * media library attachments. These tests verify the new behavior.
 */

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

	public function test_no_file_upload_returns_empty(): void {
		// When no file is in $_FILES, the sanitizer returns empty
		$context = array('name' => 'test_file');

		$result = $this->sanitizer->sanitize('some-filename.jpg', $context, $this->emitNotice);

		// Without $_FILES data, the sanitizer returns empty (no actual upload)
		self::assertSame('', $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_preserves_existing_file_data_array(): void {
		// When value is already a processed file array, it should be preserved
		$existingFile = array(
			'url'           => 'https://example.com/uploads/photo.jpg',
			'file'          => '/var/www/uploads/photo.jpg',
			'type'          => 'image/jpeg',
			'filename'      => 'photo.jpg',
			'attachment_id' => 123,
		);
		$context = array('name' => 'test_file');

		$result = $this->sanitizer->sanitize($existingFile, $context, $this->emitNotice);

		self::assertSame($existingFile, $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_preserves_multiple_existing_files(): void {
		// When value is an array of processed file arrays, it should be preserved
		$existingFiles = array(
			array(
				'url'      => 'https://example.com/uploads/photo1.jpg',
				'filename' => 'photo1.jpg',
			),
			array(
				'url'      => 'https://example.com/uploads/photo2.jpg',
				'filename' => 'photo2.jpg',
			),
		);
		$context = array('name' => 'test_file', 'multiple' => true);

		$result = $this->sanitizer->sanitize($existingFiles, $context, $this->emitNotice);

		self::assertSame($existingFiles, $result);
		self::assertSame(array(), $this->notices);
	}

	public function test_empty_input_respects_multiple_flag(): void {
		self::assertSame('', $this->sanitizer->sanitize('', array(), $this->emitNotice));
		self::assertSame(array(), $this->sanitizer->sanitize('', array('multiple' => true), $this->emitNotice));
		self::assertSame(array(), $this->notices);
	}

	public function test_null_input_returns_null(): void {
		// Base class allows null and returns it as-is
		self::assertNull($this->sanitizer->sanitize(null, array(), $this->emitNotice));
		self::assertNull($this->sanitizer->sanitize(null, array('multiple' => true), $this->emitNotice));
	}
}
