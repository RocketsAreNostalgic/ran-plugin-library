<?php
/**
 * Form component normalizer.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Form;

use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;

final class FormNormalizer extends NormalizerBase {
	/**
	 * Form-specific normalization logic.
	 */
	protected function _normalize_component_specific(array $context): array {
		// Validate and sanitize form configuration
		$action = $this->_validate_config_string($context['action'] ?? '', 'action') ?? '';
		$method = $this->_validate_choice(
			$context['method'] ?? 'POST',
			array('GET', 'POST'),
			'method',
			'POST'
		);
		$hasFiles    = $this->_sanitize_boolean($context['has_files'] ?? false, 'has_files');
		$children    = $this->_sanitize_string($context['children'] ?? '', 'children');
		$nonceAction = $this->_validate_config_string($context['nonce_action'] ?? '', 'nonce_action');
		$nonceField  = $this->_validate_config_string($context['nonce_field'] ?? '_wpnonce', 'nonce_field') ?? '_wpnonce';
		$errors      = $this->_validate_config_array($context['errors'] ?? array(), 'errors')               ?? array();
		$notices     = $this->_validate_config_array($context['notices'] ?? array(), 'notices')             ?? array();

		// Set form ID and reserve it
		$formId                      = $this->_generate_and_reserve_id($context, 'form');
		$context['attributes']['id'] = $formId;

		// Add enctype for file uploads
		if ($hasFiles) {
			$context['attributes']['enctype'] = 'multipart/form-data';
		}

		// Set method and action
		$context['attributes']['method'] = $method;
		if ($action !== '') {
			$context['attributes']['action'] = $action;
		}

		// Store normalized values back in context
		$context['action']       = $action;
		$context['method']       = $method;
		$context['has_files']    = $hasFiles;
		$context['children']     = $children;
		$context['nonce_action'] = $nonceAction;
		$context['nonce_field']  = $nonceField;
		$context['errors']       = $errors;
		$context['notices']      = $notices;

		return $context;
	}
}
