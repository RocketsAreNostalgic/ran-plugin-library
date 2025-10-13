<?php
/**
 * File upload helper for front-end forms.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Elements\FileUpload;

use InvalidArgumentException;

final class FileUploadHelper {
	/**
	 * Process a single file upload via WordPress APIs.
	 *
	 * Expected $args keys:
	 * - file_key: string Required key in $_FILES / provided files array.
	 * - nonce_action: string Optional nonce action to verify.
	 * - nonce_field: string Nonce field name (defaults to _wpnonce).
	 * - request: array|null Alternate request data for nonce lookup (defaults to $_POST).
	 * - files: array|null Alternate files array (defaults to $_FILES).
	 * - capability: string Optional capability to check with current_user_can().
	 * - upload_overrides: array Optional overrides passed to wp_handle_upload().
	 *
	 * @param array<string,mixed> $args Arguments controlling upload handling.
	 *
	 * @return array{success:bool,error?:string,error_code?:int,file?:array<string,string>} Result payload.
	 */
	public static function handle(array $args): array {
		$defaults = array(
			'file_key'         => '',
			'nonce_action'     => '',
			'nonce_field'      => '_wpnonce',
			'request'          => null,
			'files'            => null,
			'capability'       => '',
			'upload_overrides' => array(),
		);
		$args = array_merge($defaults, $args);

		$fileKey = (string) $args['file_key'];
		if ($fileKey === '') {
			throw new InvalidArgumentException('FileUploadHelper::handle() requires a file_key argument.');
		}

		$requestData = is_array($args['request']) ? $args['request'] : $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ($args['nonce_action'] !== '') {
			$nonceField = (string) $args['nonce_field'];
			$nonceValue = isset($requestData[$nonceField]) ? (string) $requestData[$nonceField] : '';
			if ($nonceValue === '' || !function_exists('wp_verify_nonce') || !wp_verify_nonce($nonceValue, (string) $args['nonce_action'])) {
				return array(
					'success'    => false,
					'error'      => 'invalid_nonce',
					'error_code' => 0,
				);
			}
		}

		if ($args['capability'] !== '' && function_exists('current_user_can')) {
			if (!current_user_can((string) $args['capability'])) {
				return array(
					'success'    => false,
					'error'      => 'insufficient_permissions',
					'error_code' => 0,
				);
			}
		}

		$files = is_array($args['files']) ? $args['files'] : $_FILES; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if (!isset($files[$fileKey])) {
			return array(
				'success'    => false,
				'error'      => 'file_missing',
				'error_code' => 0,
			);
		}

		$file = $files[$fileKey];
		if (!is_array($file) || !isset($file['error'])) {
			return array(
				'success'    => false,
				'error'      => 'invalid_file_payload',
				'error_code' => 0,
			);
		}

		if (is_array($file['error'])) {
			return array(
				'success'    => false,
				'error'      => 'multiple_files_not_supported',
				'error_code' => 0,
			);
		}

		$errorCode = (int) $file['error'];
		if ($errorCode !== UPLOAD_ERR_OK) {
			return array(
				'success'    => false,
				'error'      => self::humanReadableError($errorCode),
				'error_code' => $errorCode,
			);
		}

		if (!function_exists('wp_handle_upload')) {
			return array(
				'success'    => false,
				'error'      => 'upload_handler_missing',
				'error_code' => 0,
			);
		}

		$overrides = array_merge(array('test_form' => false), is_array($args['upload_overrides']) ? $args['upload_overrides'] : array());
		$upload    = wp_handle_upload($file, $overrides);
		if (!is_array($upload) || isset($upload['error'])) {
			return array(
				'success'    => false,
				'error'      => isset($upload['error']) ? (string) $upload['error'] : 'upload_failed',
				'error_code' => 0,
			);
		}

		return array(
			'success' => true,
			'file'    => array(
				'file' => isset($upload['file']) ? (string) $upload['file'] : '',
				'url'  => isset($upload['url']) ? (string) $upload['url'] : '',
				'type' => isset($upload['type']) ? (string) $upload['type'] : '',
			),
		);
	}

	/**
	 * Translate PHP upload error codes into identifiers.
	 */
	private static function humanReadableError(int $errorCode): string {
		switch ($errorCode) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return 'file_too_large';
			case UPLOAD_ERR_PARTIAL:
				return 'upload_incomplete';
			case UPLOAD_ERR_NO_FILE:
				return 'file_missing';
			case UPLOAD_ERR_NO_TMP_DIR:
				return 'missing_tmp_dir';
			case UPLOAD_ERR_CANT_WRITE:
				return 'cannot_write_file';
			case UPLOAD_ERR_EXTENSION:
				return 'blocked_by_extension';
			default:
				return 'upload_failed';
		}
	}
}
