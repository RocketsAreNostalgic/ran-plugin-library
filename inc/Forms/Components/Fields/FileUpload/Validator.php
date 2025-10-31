<?php
/**
 * FileUpload component validator.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\FileUpload;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;

final class Validator extends ValidatorBase {
	use WPWrappersTrait;

	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		// Check if files are required when value is null/empty
		if ($value === null || $value === '') {
			$required = $context['required'] ?? false;
			if ($required) {
				$emitWarning($this->_translate('At least one file is required.'));
				return false;
			}
			return true;
		}

		// Get WordPress allowed extensions as the base
		$wordpressAllowed = $this->_do_get_allowed_mime_types();

		// Get final allowed extensions (WordPress base, optionally restricted)
		// Configuration validation is handled in Normalizer (fail-fast principle)
		$allowedExtensions = $this->_get_allowed_extensions($context, $wordpressAllowed);

		// Handle existing files array
		if (isset($context['existing_files']) && Validate::basic()->is_array()($context['existing_files'])) {
			foreach ($context['existing_files'] as $file) {
				if (!$this->_validate_single_file((string) $file, $allowedExtensions, $emitWarning)) {
					return false;
				}
			}
		}

		// Handle single file or multiple files in value
		if (Validate::basic()->is_string()($value)) {
			return $this->_validate_single_file($value, $allowedExtensions, $emitWarning);
		}

		if (Validate::basic()->is_array()($value)) {
			foreach ($value as $file) {
				if (!$this->_validate_single_file((string) $file, $allowedExtensions, $emitWarning)) {
					return false;
				}
			}
			return true;
		}

		$emitWarning($this->_translate('File value must be a string or array of strings.'));
		return false;
	}

	protected function _allow_null(): bool {
		// File uploads can be optional
		return true;
	}

	/**
	 * Validate a single file path or URL.
	 */
	private function _validate_single_file(string $file, array $allowedExtensions, callable $emitWarning): bool {
		if (empty($file)) {
			return true; // Empty files are handled at the top level
		}

		// If it looks like a URL, validate the URL format first
		if ($this->_is_url($file)) {
			if (!Validate::format()->url()($file)) {
				$emitWarning($this->_translate('Invalid URL format for file upload.'));
				return false;
			}
		}

		// Use the file extension validator from ValidateFormatGroup
		$isValidExtension = empty($allowedExtensions)
			? Validate::format()->file_extension()($file)
			: Validate::format()->file_extension($allowedExtensions)($file);

		if (!$isValidExtension) {
			$extension = $this->_extract_file_extension($file);
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
