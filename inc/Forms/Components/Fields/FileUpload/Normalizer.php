<?php
/**
 * File upload component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\FileUpload;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class Normalizer extends NormalizerBase {
	use WPWrappersTrait;

	/**
	 * Warn if accept types may not be allowed by WordPress.
	 *
	 * The accept attribute is client-side only and easily bypassed. Real validation
	 * happens in wp_handle_upload(). This warning helps developers catch misconfigurations
	 * early during development.
	 *
	 * @param array<int,string> $acceptTypes Array of accept values (MIME types, extensions, wildcards)
	 */
	private function _warn_disallowed_accept_types(array $acceptTypes): void {
		if (!defined('WP_DEBUG') || !WP_DEBUG) {
			return;
		}

		$wpAllowedMimes = $this->_do_get_allowed_mime_types();
		$wpExtensions   = array();
		$wpMimeTypes    = array();

		// Build lookup arrays from WP allowed MIME types (format: "ext|ext2" => "mime/type")
		foreach ($wpAllowedMimes as $extPattern => $mimeType) {
			$wpMimeTypes[] = $mimeType;
			foreach (explode('|', $extPattern) as $ext) {
				$wpExtensions[] = strtolower($ext);
			}
		}

		$disallowed = array();

		foreach ($acceptTypes as $acceptValue) {
			$acceptValue = trim($acceptValue);
			if ($acceptValue === '') {
				continue;
			}

			// Skip wildcards like "image/*", "video/*" - these are valid HTML accept patterns
			if (str_contains($acceptValue, '/*')) {
				continue;
			}

			// Check extension format (.pdf, .jpg)
			if (str_starts_with($acceptValue, '.')) {
				$ext = strtolower(ltrim($acceptValue, '.'));
				if (!in_array($ext, $wpExtensions, true)) {
					$disallowed[] = $acceptValue;
				}
				continue;
			}

			// Check MIME type format (application/pdf, image/jpeg)
			if (str_contains($acceptValue, '/')) {
				if (!in_array($acceptValue, $wpMimeTypes, true)) {
					$disallowed[] = $acceptValue;
				}
				continue;
			}

			// Plain extension without dot (pdf, jpg)
			$ext = strtolower($acceptValue);
			if (!in_array($ext, $wpExtensions, true)) {
				$disallowed[] = $acceptValue;
			}
		}

		if (!empty($disallowed)) {
			$this->logger->warning(
				sprintf(
					'FileUpload accept attribute contains types not allowed by WordPress: %s. ' .
					'These files will be rejected by wp_handle_upload(). ' .
					'To allow additional MIME types, use the "upload_mimes" filter.',
					implode(', ', $disallowed)
				),
				array(
					'disallowed_types' => $disallowed,
					'accept_values'    => $acceptTypes,
				)
			);
		}
	}

	/**
	 * Validate allowed_extensions configuration during normalization (fail-fast).
	 *
	 * @param array $context Component context
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	private function _validate_allowed_extensions_config(array $context): void {
		// If no custom restrictions, configuration is valid
		if (!isset($context['allowed_extensions']) || !Validate::basic()->is_array()($context['allowed_extensions'])) {
			return;
		}

		// Get WordPress allowed extensions
		$wordpressAllowed = $this->_do_get_allowed_mime_types();

		// Normalize custom extensions to lowercase
		$customExtensions = $this->_normalize_extensions($context['allowed_extensions']);

		// Validate that all custom extensions are in WordPress allowed list
		$invalidExtensions = array_diff($customExtensions, $wordpressAllowed);
		if (!empty($invalidExtensions)) {
			$error = sprintf(
				'Extensions not allowed by WordPress: %s. WordPress allowed extensions: %s',
				implode(', ', $invalidExtensions),
				implode(', ', $wordpressAllowed)
			);

			$this->logger->error('Invalid file upload extension configuration', array(
				'invalid_extensions' => $invalidExtensions,
				'wordpress_allowed'  => $wordpressAllowed,
				'custom_extensions'  => $customExtensions,
				'normalizer_class'   => static::class
			));

			throw new \InvalidArgumentException($error);
		}
	}

	/**
	 * Normalize component-specific context.
	 *
	 * @param array $context Component context
	 *
	 * @return array Normalized context
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	protected function _validate_basic_component_config(array $context): void {
		// Validate allowed_extensions configuration (fail-fast principle)
		$this->_validate_allowed_extensions_config($context);
	}

	protected function _normalize_component_specific(array $context): array {
		// Validate required name (file uploads must have names to function)
		$name = $this->_sanitize_string($context['name'] ?? '', 'name');
		if ($name === '') {
			if (isset($context['id']) && (string) $context['id'] !== '') {
				$name = $this->_sanitize_string($context['id'], 'id fallback for name');
			} else {
				$error = 'components.file-upload requires a "name" value.';
				$this->logger->error('Missing required name value', array(
					'context_keys' => array_keys($context),
					'has_name'     => isset($context['name']),
					'has_id'       => isset($context['id'])
				));
				throw new \InvalidArgumentException($error);
			}
		}

		// Set file input attributes
		$context['attributes']['name'] = $name;
		$context['attributes']['type'] = 'file';

		// Handle multiple files using base class boolean sanitization
		$multiple = $this->_sanitize_boolean($context['multiple'] ?? false, 'multiple');
		if ($multiple) {
			$context['attributes']['multiple'] = 'multiple';
		}

		// Handle accept attribute
		$accept = $context['accept'] ?? null;
		if ($accept !== null) {
			if (is_array($accept)) {
				$sanitizedAccept = array();
				foreach ($accept as $entry) {
					$sanitizedAccept[] = $this->_sanitize_string($entry ?? '', 'accept');
				}
				$context['attributes']['accept'] = implode(',', $sanitizedAccept);
				$accept                          = $sanitizedAccept;
			} else {
				$accept                          = $this->_sanitize_string($accept, 'accept');
				$context['attributes']['accept'] = $accept;
				$accept                          = array($accept); // Normalize to array for validation
			}

			// Warn about accept values that may not be allowed by WordPress
			$this->_warn_disallowed_accept_types($accept);
		}

		// Build template context
		$context['name']           = $name;
		$context['multiple']       = $multiple;
		$context['accept']         = $accept;
		$context['existing_files'] = $this->_validate_config_array($context['existing_files'] ?? null, 'existing_files') ?? array();

		return $context;
	}
}
