<?php
/**
 * FileUpload component sanitizer.
 *
 * Sanitizes file upload input by normalizing file paths/URLs and
 * filtering to allowed extensions.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\FileUpload;

use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Forms\Validation\Helpers;
use Ran\PluginLib\Forms\Component\Sanitize\SanitizerBase;

final class Sanitizer extends SanitizerBase {
	/**
	 * Sanitize file upload value.
	 *
	 * Handles both single file (string) and multiple files (array).
	 * Sanitizes file paths/URLs and filters to allowed extensions.
	 *
	 * @param mixed               $value      The submitted value (string or array).
	 * @param array<string,mixed> $context    The field context.
	 * @param callable            $emitNotice Callback to emit sanitization notices.
	 *
	 * @return string|array<int,string> The sanitized file path(s).
	 */
	protected function _sanitize_component(mixed $value, array $context, callable $emitNotice): mixed {
		$multiple = Helpers::sanitizeBoolean($context['multiple'] ?? false, 'multiple_upload', $this->logger);

		// Empty value
		if ($value === '' || $value === null) {
			return $multiple ? array() : '';
		}

		// Get allowed extensions from context
		$allowedExtensions = $this->_get_allowed_extensions($context);

		// Handle array of files
		if (is_array($value)) {
			$sanitized = array();
			foreach ($value as $file) {
				$sanitizedFile = $this->_sanitize_single_file($file, $allowedExtensions, $emitNotice);
				if ($sanitizedFile !== '') {
					$sanitized[] = $sanitizedFile;
				}
			}
			return $multiple ? $sanitized : ($sanitized[0] ?? '');
		}

		// Handle single file
		$sanitizedFile = $this->_sanitize_single_file($value, $allowedExtensions, $emitNotice);

		return $multiple ? ($sanitizedFile !== '' ? array($sanitizedFile) : array()) : $sanitizedFile;
	}

	/**
	 * Sanitize a single file path or URL.
	 *
	 * @param mixed         $file              The file path or URL.
	 * @param array<string> $allowedExtensions Allowed file extensions.
	 * @param callable      $emitNotice        Callback to emit notices.
	 *
	 * @return string The sanitized file path or empty string if invalid.
	 */
	private function _sanitize_single_file(mixed $file, array $allowedExtensions, callable $emitNotice): string {
		if (!is_scalar($file) || $file === '') {
			return '';
		}

		$stringValue = (string) $file;

		// Check if it's a URL
		if ($this->_is_url($stringValue)) {
			$sanitized = esc_url_raw($stringValue);
		} else {
			// Sanitize as file name/path
			$sanitized = sanitize_file_name($stringValue);
		}

		if ($sanitized === '') {
			return '';
		}

		// Check extension if restrictions are defined
		if (!empty($allowedExtensions)) {
			$extension = $this->_extract_extension($sanitized);
			if ($extension !== '' && !in_array($extension, $allowedExtensions, true)) {
				$emitNotice($this->_translate('File with disallowed extension was removed.'));
				$this->logger->debug('FileUpload Sanitizer: Removed file with disallowed extension', array(
					'file'      => $sanitized,
					'extension' => $extension,
					'allowed'   => $allowedExtensions,
				));
				return '';
			}
		}

		return $sanitized;
	}

	/**
	 * Get allowed extensions from context.
	 *
	 * @param array<string,mixed> $context The field context.
	 *
	 * @return array<string> Normalized array of allowed extensions (lowercase).
	 */
	private function _get_allowed_extensions(array $context): array {
		if (!isset($context['allowed_extensions']) || !is_array($context['allowed_extensions'])) {
			return array();
		}

		// Normalize to lowercase
		return array_map('strtolower', array_map('trim', $context['allowed_extensions']));
	}

	/**
	 * Extract file extension from path or URL.
	 *
	 * @param string $filePath The file path or URL.
	 *
	 * @return string The lowercase extension or empty string.
	 */
	private function _extract_extension(string $filePath): string {
		// Handle URLs by extracting path component
		if ($this->_is_url($filePath)) {
			$parsed   = parse_url($filePath);
			$filePath = $parsed['path'] ?? '';
		}

		$extension = pathinfo($filePath, PATHINFO_EXTENSION);

		return strtolower($extension);
	}

	/**
	 * Check if a string is a URL.
	 *
	 * @param string $string The string to check.
	 *
	 * @return bool True if the string is a URL.
	 */
	private function _is_url(string $string): bool {
		return Validate::format()->url()($string);
	}
}
