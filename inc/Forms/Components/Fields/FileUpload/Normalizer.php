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
					'component_type' => $this->componentType,
					'context_keys'   => array_keys($context),
					'has_name'       => isset($context['name']),
					'has_id'         => isset($context['id'])
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
				$context['attributes']['accept'] = implode(',', array_map('strval', $accept));
			} else {
				$context['attributes']['accept'] = $this->_sanitize_string($accept, 'accept');
			}
		}

		// Build template context
		$context['name']           = $name;
		$context['multiple']       = $multiple;
		$context['accept']         = $accept;
		$context['existing_files'] = $this->_validate_config_array($context['existing_files'] ?? null, 'existing_files') ?? array();

		return $context;
	}
}
