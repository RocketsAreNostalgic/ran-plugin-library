<?php
/**
 * FileUpload component validator.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\FileUpload;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;
use Ran\PluginLib\Forms\Validation\Helpers;

final class Validator extends ValidatorBase {
	use WPWrappersTrait;

	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		$multiple = Helpers::sanitizeBoolean($context['multiple'] ?? false, 'multiple_upload', $this->logger);

		// Check if files are required when value is null/empty
		if ($this->_is_empty_value($value)) {
			$required = $context['required'] ?? false;
			if ($required) {
				$emitWarning($this->_translate('At least one file is required.'));
				return false;
			}
			return true;
		}

		// Handle the new array format from Sanitizer (with url, filename, etc.)
		if (is_array($value)) {
			// Single file array format: ['url' => '...', 'filename' => '...', ...]
			if (isset($value['url'])) {
				return $this->_validate_file_data($value, $emitWarning);
			}

			// Multiple files: array of file data arrays
			if (isset($value[0]) && is_array($value[0])) {
				foreach ($value as $fileData) {
					if (!$this->_validate_file_data($fileData, $emitWarning)) {
						return false;
					}
				}
				return true;
			}

			// Legacy: array of strings
			foreach ($value as $file) {
				if (!$this->_validate_legacy_file($file, $context, $emitWarning)) {
					return false;
				}
			}
			return true;
		}

		// Legacy: single string value
		if (is_string($value)) {
			return $this->_validate_legacy_file($value, $context, $emitWarning);
		}

		$emitWarning($this->_translate('Invalid file upload value.'));
		return false;
	}

	/**
	 * Check if the value is considered empty.
	 */
	private function _is_empty_value(mixed $value): bool {
		if ($value === null || $value === '') {
			return true;
		}
		if (is_array($value) && empty($value)) {
			return true;
		}
		return false;
	}

	/**
	 * Validate file data in the new array format.
	 */
	private function _validate_file_data(array $fileData, callable $emitWarning): bool {
		$url = $fileData['url'] ?? '';

		if (empty($url)) {
			return true; // Empty is handled at top level
		}

		// Validate URL format
		if (!Validate::format()->url()($url)) {
			$emitWarning($this->_translate('Invalid file URL.'));
			return false;
		}

		return true;
	}

	protected function _allow_null(): bool {
		// File uploads can be optional
		return true;
	}

	/**
	 * Validate a legacy file value (string path or URL).
	 */
	private function _validate_legacy_file(mixed $file, array $context, callable $emitWarning): bool {
		try {
			$normalizedFile = Helpers::sanitizeString($file, 'file_reference', $this->logger);
		} catch (\InvalidArgumentException $exception) {
			$this->logger->warning('File reference must be scalar', array(
				'provided_type'   => gettype($file),
				'validator_class' => static::class
			));
			return false;
		}

		if ($normalizedFile === '') {
			return true; // Empty files are handled at the top level
		}

		// If it looks like a URL, validate the URL format first
		if ($this->_is_url($normalizedFile)) {
			if (!Validate::format()->url()($normalizedFile)) {
				$emitWarning($this->_translate('Invalid URL format for file upload.'));
				return false;
			}
		}

		// Get WordPress allowed extensions as the base
		$wordpressAllowed  = $this->_do_get_allowed_mime_types();
		$allowedExtensions = $this->_get_allowed_extensions($context, $wordpressAllowed);

		// Use the file extension validator from ValidateFormatGroup
		$isValidExtension = empty($allowedExtensions)
			? Validate::format()->file_extension()($normalizedFile)
			: Validate::format()->file_extension($allowedExtensions)($normalizedFile);

		if (!$isValidExtension) {
			$extension = $this->_extract_file_extension($normalizedFile);
			if (empty($extension)) {
				$emitWarning($this->_translate('File must have a valid extension.'));
			} else {
				$allowedList = empty($allowedExtensions) ? $this->_translate('WordPress defaults') : implode(', ', $allowedExtensions);
				$emitWarning(sprintf(
					$this->_translate('File extension ".%s" is not allowed. Allowed extensions: %s'),
					$extension,
					$allowedList
				));
			}
			return false;
		}

		return true;
	}

	/**
	 * Extract file extension from file path or URL and normalize to lowercase.
	 */
	private function _extract_file_extension(string $filePath): string {
		// Handle URLs by extracting the path component
		if ($this->_is_url($filePath)) {
			$parsedUrl = parse_url($filePath);
			$filePath  = $parsedUrl['path'] ?? '';
		}

		// Get the file extension
		$extension = pathinfo($filePath, PATHINFO_EXTENSION);

		// Convert to lowercase for case-insensitive comparison
		return strtolower($extension);
	}

	/**
	 * Get allowed extensions: WordPress base, optionally restricted by custom list.
	 * Configuration validation is handled in the Normalizer (fail-fast principle).
	 *
	 * @param array $context Component context
	 * @param array $wordpressAllowed WordPress allowed extensions
	 * @return array Array of allowed extensions
	 */
	private function _get_allowed_extensions(array $context, array $wordpressAllowed): array {
		// If no custom restrictions, use all WordPress allowed extensions
		if (!isset($context['allowed_extensions']) || !Validate::basic()->is_array()($context['allowed_extensions'])) {
			return $wordpressAllowed;
		}

		// Normalize custom extensions to lowercase and return intersection
		// (Configuration validation already done in Normalizer)
		$customExtensions = $this->_normalize_extensions($context['allowed_extensions']);
		return array_intersect($customExtensions, $wordpressAllowed);
	}

	/**
	 * Normalize extensions array to lowercase.
	 */
	private function _normalize_extensions(array $extensions): array {
		return array_map('strtolower', array_map('trim', $extensions));
	}

	/**
	 * Check if a string looks like a URL.
	 */
	private function _is_url(string $string): bool {
		return Validate::format()->url()($string);
	}
}
