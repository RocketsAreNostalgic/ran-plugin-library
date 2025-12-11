<?php
/**
 * Text input validator.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Input;

use Ran\PluginLib\Util\Validate;
use Ran\PluginLib\Forms\Validation\Helpers;
use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;

class Validator extends ValidatorBase {
	/** @var array<int,string> */
	private array $validInputTypes = array(
		'checkbox', 'color', 'date', 'datetime-local', 'email', 'file', 'hidden',
		'image', 'number', 'password', 'radio', 'range', 'reset',
		'search', 'submit', 'tel', 'text', 'time', 'url',
	);

	protected function _validate_component(mixed $value, array $context, callable $emitWarning): bool {
		if (!$this->_validate_scalar_or_null($value)) {
			return false;
		}

		$type = isset($context['input_type']) ? (string) $context['input_type'] : 'text';

		// Validate input type is supported
		if (!in_array($type, $this->validInputTypes, true)) {
			$this->logger->warning('Unsupported input type', array(
				'input_type'      => $type,
				'supported_types' => $this->validInputTypes,
				'validator_class' => static::class
			));
			return false;
		}

		// Check if input is required when value is null/empty
		if ($value === null || $value === '') {
			$required = $context['required'] ?? false;
			if ($required && $this->_requires_value($type)) {
				$emitWarning($this->_translate('This field is required.'));
				return false;
			}
			return true;
		}

		$stringValue = (string) $value;

		// Validate based on input type
		return $this->_validate_by_input_type($type, $stringValue, $context, $emitWarning);
	}

	/**
	 * Check if the input type requires a value when marked as required.
	 * Some types like submit, reset, image don't need user-provided values.
	 */
	private function _requires_value(string $type): bool {
		$nonValueTypes = array('submit', 'reset', 'image', 'hidden');
		return !in_array($type, $nonValueTypes, true);
	}

	/**
	 * Validate value based on specific input type.
	 */
	private function _validate_by_input_type(string $type, string $value, array $context, callable $emitWarning): bool {
		switch ($type) {
			case 'email':
				return $this->_validate_email($value, $emitWarning);

			case 'url':
				return $this->_validate_url($value, $emitWarning);

			case 'tel':
				return $this->_validate_phone($value, $emitWarning);

			case 'number':
				return $this->_validate_number($value, $context, $emitWarning);

			case 'range':
				return $this->_validate_range($value, $context, $emitWarning);

			case 'date':
				return $this->_validate_date($value, $emitWarning);

			case 'datetime-local':
				return $this->_validate_datetime_local($value, $emitWarning);

			case 'time':
				return $this->_validate_time($value, $emitWarning);


			case 'color':
				return $this->_validate_color($value, $emitWarning);

			case 'text':
			case 'search':
			case 'password':
				return $this->_validate_text($value, $context, $emitWarning);

			case 'checkbox':
			case 'radio':
				return $this->_validate_choice_input($value, $context, $emitWarning);

			case 'file':
				return $this->_validate_file($value, $emitWarning);

			case 'hidden':
			case 'submit':
			case 'reset':
			case 'image':
				// These types don't need validation
				return true;

			default:
				return true;
		}
	}

	private function _validate_email(string $value, callable $emitWarning): bool {
		if (!Validate::format()->email()($value)) {
			$emitWarning($this->_translate('Please enter a valid email address.'));
			return false;
		}
		return true;
	}

	private function _validate_url(string $value, callable $emitWarning): bool {
		if (!Validate::format()->url()($value)) {
			$emitWarning($this->_translate('Please enter a valid URL (e.g., https://example.com).'));
			return false;
		}
		return true;
	}

	private function _validate_phone(string $value, callable $emitWarning): bool {
		if (!Validate::format()->phone()($value)) {
			$emitWarning($this->_translate('Please enter a valid phone number.'));
			return false;
		}
		return true;
	}

	private function _validate_number(string $value, array $context, callable $emitWarning): bool {
		if (!is_numeric($value)) {
			$emitWarning($this->_translate('Please enter a valid number.'));
			return false;
		}

		$numValue = (float) $value;

		// Check min/max constraints
		if (isset($context['min']) && $numValue < (float) $context['min']) {
			$emitWarning(sprintf($this->_translate('Number must be at least %s.'), $context['min']));
			return false;
		}

		if (isset($context['max']) && $numValue > (float) $context['max']) {
			$emitWarning(sprintf($this->_translate('Number must be no more than %s.'), $context['max']));
			return false;
		}

		return true;
	}

	private function _validate_range(string $value, array $context, callable $emitWarning): bool {
		return $this->_validate_number($value, $context, $emitWarning);
	}

	private function _validate_date(string $value, callable $emitWarning): bool {
		if (!Validate::temporal()->date()($value)) {
			$emitWarning($this->_translate('Please enter a valid date (YYYY-MM-DD).'));
			return false;
		}
		return true;
	}

	private function _validate_datetime_local(string $value, callable $emitWarning): bool {
		// datetime-local format: YYYY-MM-DDTHH:MM or YYYY-MM-DDTHH:MM:SS
		if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value)) {
			$emitWarning($this->_translate('Please enter a valid date and time (YYYY-MM-DDTHH:MM).'));
			return false;
		}
		return true;
	}

	private function _validate_time(string $value, callable $emitWarning): bool {
		if (!Validate::temporal()->time()($value)) {
			$emitWarning($this->_translate('Please enter a valid time (HH:MM).'));
			return false;
		}
		return true;
	}

	private function _validate_month(string $value, callable $emitWarning): bool {
		// Month format: YYYY-MM
		if (!preg_match('/^\d{4}-\d{2}$/', $value)) {
			$emitWarning($this->_translate('Please enter a valid month (YYYY-MM).'));
			return false;
		}
		return true;
	}

	private function _validate_week(string $value, callable $emitWarning): bool {
		// Week format: YYYY-W##
		if (!preg_match('/^\d{4}-W\d{2}$/', $value)) {
			$emitWarning($this->_translate('Please enter a valid week (YYYY-W##).'));
			return false;
		}
		return true;
	}

	private function _validate_color(string $value, callable $emitWarning): bool {
		// Color format: #RRGGBB
		if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
			$emitWarning($this->_translate('Please enter a valid color (e.g., #FF0000).'));
			return false;
		}
		return true;
	}

	private function _validate_text(string $value, array $context, callable $emitWarning): bool {
		$cleanValue = Helpers::sanitizeString($value, 'input_value', $this->logger);

		$minlength = isset($context['minlength']) ? (int) $context['minlength'] : null;
		if ($minlength !== null && !Helpers::validateLength($cleanValue, 'input_value', $this->logger, $minlength, null)) {
			$emitWarning(sprintf($this->_translate('Text must be at least %d characters long.'), $minlength));
			return false;
		}

		$maxlength = isset($context['maxlength']) ? (int) $context['maxlength'] : null;
		if ($maxlength !== null && !Helpers::validateLength($cleanValue, 'input_value', $this->logger, null, $maxlength)) {
			$emitWarning(sprintf($this->_translate('Text must be no more than %d characters long.'), $maxlength));
			return false;
		}

		if (isset($context['pattern'])) {
			$pattern = (string) $context['pattern'];
			if (@preg_match('/' . $pattern . '/', $cleanValue) !== 1) {
				$emitWarning($this->_translate('Please enter a value that matches the required format.'));
				return false;
			}
		}

		return true;
	}

	private function _validate_choice_input(string $value, array $context, callable $emitWarning): bool {
		// For checkbox/radio inputs, validate against expected value
		$expectedValue = isset($context['value']) ? (string) $context['value'] : 'on';
		return $value === $expectedValue;
	}

	/**
	 * Validate file inputs.
	 *
	 * @param string $value File path or name
	 * @param callable $emitWarning Callback to emit warning messages
	 * @return bool True if valid, false otherwise
	 */
	private function _validate_file(string $value, callable $emitWarning): bool {
		$cleanValue = Helpers::sanitizeString($value, 'file_input', $this->logger);

		if ($cleanValue === '') {
			$emitWarning($this->_translate('Please select a file.'));
			return false;
		}

		return true;
	}
}
