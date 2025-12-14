<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Forms\Services\FormsFileUploadService;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ran\PluginLib\Forms\Services\FormsFileUploadService
 */
final class FormsFileUploadServiceTest extends TestCase {
	public function test_process_uploaded_files_returns_empty_when_main_option_not_present(): void {
		$logger = new CollectingLogger();
		$svc    = $this->create_service($logger, 'opt');

		$result = $svc->process_uploaded_files(array());

		self::assertSame(array(), $result);
	}

	public function test_process_uploaded_files_skips_no_file_and_upload_error_and_logs_warning(): void {
		$logger = new CollectingLogger();
		$called = array(
			'wp_handle_upload' => 0,
		);

		$svc = $this->create_service(
			$logger,
			'opt',
			wp_handle_upload: function (array $file, array $overrides = array(), string $time = '') use (&$called): array {
				$called['wp_handle_upload']++;
				return array();
			}
		);

		$files = array(
			'opt' => array(
				'name'     => array('a' => '', 'b' => 'fileb.txt', 'c' => 'filec.txt'),
				'type'     => array('a' => '', 'b' => 'text/plain', 'c' => 'text/plain'),
				'tmp_name' => array('a' => '', 'b' => '/tmp/b', 'c' => '/tmp/c'),
				'error'    => array('a' => \UPLOAD_ERR_NO_FILE, 'b' => \UPLOAD_ERR_INI_SIZE, 'c' => \UPLOAD_ERR_OK),
				'size'     => array('a' => 0, 'b' => 123, 'c' => 456),
			),
		);

		// Make the only OK file fail the is_uploaded_file check so we can focus on skip behavior.
		$svc = $this->create_service(
			$logger,
			'opt',
			is_uploaded_file: static function (string $path): bool {
				return false;
			},
			wp_handle_upload: function (array $file, array $overrides = array(), string $time = '') use (&$called): array {
				$called['wp_handle_upload']++;
				return array();
			}
		);

		$result = $svc->process_uploaded_files($files);

		self::assertSame(array(), $result);
		self::assertSame(0, $called['wp_handle_upload']);

		$warnings = array_values(array_filter($logger->get_logs(), static function (array $entry): bool {
			return $entry['level'] === 'warning';
		}));

		self::assertCount(1, $warnings);
		self::assertSame('FormsFileUploadService.process_uploaded_files: Upload error', $warnings[0]['message']);
		self::assertSame('b', $warnings[0]['context']['field']);
		self::assertSame(\UPLOAD_ERR_INI_SIZE, $warnings[0]['context']['error_code']);
	}

	public function test_process_single_file_upload_returns_null_when_tmp_name_missing_or_not_uploaded(): void {
		$logger = new CollectingLogger();
		$svc    = $this->create_service($logger, 'opt', is_uploaded_file: static function (string $path): bool {
			return false;
		});

		self::assertNull($svc->process_single_file_upload(array('name' => 'a.txt')));
		self::assertNull($svc->process_single_file_upload(array('name' => 'a.txt', 'tmp_name' => '/tmp/a')));
	}

	public function test_process_single_file_upload_returns_null_and_logs_when_wp_handle_upload_errors(): void {
		$logger = new CollectingLogger();
		$svc    = $this->create_service(
			$logger,
			'opt',
			is_uploaded_file: static function (string $path): bool {
				return true;
			},
			wp_handle_upload: static function (array $file, array $overrides = array(), string $time = ''): array {
				return array('error' => 'nope');
			}
		);

		$result = $svc->process_single_file_upload(array(
			'name'     => 'bad.txt',
			'tmp_name' => '/tmp/bad',
		));

		self::assertNull($result);

		$warnings = array_values(array_filter($logger->get_logs(), static function (array $entry): bool {
			return $entry['level'] === 'warning';
		}));

		self::assertCount(1, $warnings);
		self::assertSame('FormsFileUploadService.process_single_file_upload: Upload failed', $warnings[0]['message']);
		self::assertSame('nope', $warnings[0]['context']['error']);
		self::assertSame('bad.txt', $warnings[0]['context']['file']);
	}

	public function test_process_single_file_upload_returns_file_data_and_attachment_id_on_success(): void {
		$logger = new CollectingLogger();
		$called = array(
			'sanitize_file_name'              => 0,
			'wp_insert_attachment'            => 0,
			'load_image_library'              => 0,
			'wp_generate_attachment_metadata' => 0,
			'wp_update_attachment_metadata'   => 0,
		);

		$svc = $this->create_service(
			$logger,
			'opt',
			is_uploaded_file: static function (string $path): bool {
				return true;
			},
			wp_handle_upload: static function (array $file, array $overrides = array(), string $time = ''): array {
				return array(
					'url'  => 'https://example.com/u/file.jpg',
					'file' => '/var/www/file.jpg',
					'type' => 'image/jpeg',
				);
			},
			sanitize_file_name: function (string $filename) use (&$called): string {
				$called['sanitize_file_name']++;
				return 'sanitized-' . $filename;
			},
			wp_insert_attachment: function (array $args, string $file = '', int $parent = 0, bool $wp_error = false) use (&$called): int {
				$called['wp_insert_attachment']++;
				return 123;
			},
			is_wp_error: static function (mixed $thing): bool {
				return false;
			},
			wp_generate_attachment_metadata: function (int $attachment_id, string $file) use (&$called): array {
				$called['wp_generate_attachment_metadata']++;
				return array('sizes' => array());
			},
			wp_update_attachment_metadata: function (int $attachment_id, array $data) use (&$called): int {
				$called['wp_update_attachment_metadata']++;
				return 1;
			},
			load_image_library: function () use (&$called): void {
				$called['load_image_library']++;
			}
		);

		$result = $svc->process_single_file_upload(array(
			'name'     => 'orig.jpg',
			'tmp_name' => '/tmp/orig',
		));

		self::assertIsArray($result);
		self::assertSame('https://example.com/u/file.jpg', $result['url']);
		self::assertSame('/var/www/file.jpg', $result['file']);
		self::assertSame('image/jpeg', $result['type']);
		self::assertSame('sanitized-orig.jpg', $result['filename']);
		self::assertSame(123, $result['attachment_id']);

		self::assertSame(1, $called['sanitize_file_name']);
		self::assertSame(1, $called['wp_insert_attachment']);
		self::assertSame(1, $called['load_image_library']);
		self::assertSame(1, $called['wp_generate_attachment_metadata']);
		self::assertSame(1, $called['wp_update_attachment_metadata']);
	}

	public function test_create_media_attachment_returns_null_and_logs_when_wp_error(): void {
		$logger     = new CollectingLogger();
		$fake_error = new class {
			public function get_error_message(): string {
				return 'boom';
			}
		};

		$svc = $this->create_service(
			$logger,
			'opt',
			wp_insert_attachment: static function (array $args, string $file = '', int $parent = 0, bool $wp_error = false) use ($fake_error) {
				return $fake_error;
			},
			is_wp_error: static function (mixed $thing): bool {
				return true;
			}
		);

		$result = $svc->create_media_attachment(array(
			'url'  => 'https://example.com/u/file.jpg',
			'file' => '/var/www/file.jpg',
			'type' => 'image/jpeg',
		));

		self::assertNull($result);

		$warnings = array_values(array_filter($logger->get_logs(), static function (array $entry): bool {
			return $entry['level'] === 'warning';
		}));

		self::assertCount(1, $warnings);
		self::assertSame('FormsFileUploadService.create_media_attachment: Failed to create attachment', $warnings[0]['message']);
		self::assertSame('boom', $warnings[0]['context']['error']);
	}

	/**
	 * @param callable(string):bool|null $is_uploaded_file
	 * @param callable(array,array,string):array|null $wp_handle_upload
	 * @param callable(string):string|null $sanitize_file_name
	 * @param callable(array,string,int,bool):mixed|null $wp_insert_attachment
	 * @param callable(mixed):bool|null $is_wp_error
	 * @param callable(int,string):array|null $wp_generate_attachment_metadata
	 * @param callable(int,array):mixed|null $wp_update_attachment_metadata
	 * @param callable():void|null $load_image_library
	 */
	private function create_service(
		CollectingLogger $logger,
		string $main_option,
		?callable $is_uploaded_file = null,
		?callable $wp_handle_upload = null,
		?callable $sanitize_file_name = null,
		?callable $wp_insert_attachment = null,
		?callable $is_wp_error = null,
		?callable $wp_generate_attachment_metadata = null,
		?callable $wp_update_attachment_metadata = null,
		?callable $load_image_library = null
	): FormsFileUploadService {
		return new FormsFileUploadService(
			$logger,
			$main_option,
			$is_uploaded_file ?? static function (string $path): bool {
				return true;
			},
			$wp_handle_upload ?? static function (array $file, array $overrides = array(), string $time = ''): array {
				return array('error' => 'wp_handle_upload not stubbed');
			},
			$sanitize_file_name ?? static function (string $filename): string {
				return $filename;
			},
			$wp_insert_attachment ?? static function (array $args, string $file = '', int $parent = 0, bool $wp_error = false): int {
				return 0;
			},
			$is_wp_error ?? static function (mixed $thing): bool {
				return false;
			},
			$wp_generate_attachment_metadata ?? static function (int $attachment_id, string $file): array {
				return array();
			},
			$wp_update_attachment_metadata ?? static function (int $attachment_id, array $data): int|false {
				return false;
			},
			$load_image_library ?? static function (): void {
			}
		);
	}
}
