<?php
/**
 * Media picker component validator.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\MediaPicker;

use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;
use Ran\PluginLib\Forms\Validation\Helpers;

final class Validator extends ValidatorBase {
	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		// Media picker can accept null (no selection)
		if ($value === null || $value === '') {
			$required = $context['required'] ?? false;
			if ($required) {
				$emitWarning($this->_translate('Media selection is required.'));
				return false;
			}
			return true;
		}

		$multiple = Helpers::sanitizeBoolean($context['multiple'] ?? false, 'media_multiple', $this->logger);

		if ($multiple) {
			// Multiple selection: expect array of media IDs
			if (!$this->_validate_multiple_media_ids($value, $emitWarning)) {
				return false;
			}
		} else {
			// Single selection: expect single media ID
			if (!$this->_validate_single_media_id($value, $emitWarning)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate single media ID.
	 */
	private function _validate_single_media_id(mixed $value, callable $emitWarning): bool {
		// Should be a positive integer (WordPress attachment ID)
		try {
			$stringValue = Helpers::sanitizeString($value, 'media_id', $this->logger);
		} catch (\InvalidArgumentException $exception) {
			$this->logger->warning('Media ID must be a scalar value', array(
				'provided_type'   => gettype($value),
				'validator_class' => static::class
			));
			return false;
		}

		// Must be a positive integer
		if (!Validate::basic()->is_int()($stringValue)) {
			$this->logger->warning('Media ID must be a valid integer', array(
				'provided_value'  => $stringValue,
				'validator_class' => static::class
			));
			return false;
		}

		$intValue = (int) $stringValue;
		if ($intValue <= 0) {
			$this->logger->warning('Media ID must be a positive integer', array(
				'provided_value'  => $intValue,
				'validator_class' => static::class
			));
			return false;
		}

		return true;
	}

	/**
	 * Validate multiple media IDs.
	 */
	private function _validate_multiple_media_ids(mixed $value, callable $emitWarning): bool {
		if (!Validate::basic()->is_array()($value)) {
			$this->logger->warning('Multiple media selection must be an array', array(
				'provided_type'   => gettype($value),
				'validator_class' => static::class
			));
			return false;
		}

		// Empty array is valid (no selections)
		if (empty($value)) {
			return true;
		}

		// Each item should be a valid media ID
		foreach ($value as $index => $mediaId) {
			if (!$this->_validate_single_media_id($mediaId, $emitWarning)) {
				return false;
			}
		}

		return true;
	}



	protected function _log_boolean_coercion(mixed $value, array $context): void {
		$this->logger->warning('Boolean value passed to media picker (expected integer media ID)', array(
			'boolean_value'   => $value,
			'validator_class' => static::class,
			'multiple'        => !empty($context['multiple']),
			'context_keys'    => array_keys($context)
		));
	}
}
