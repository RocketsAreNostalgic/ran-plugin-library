<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Util\Logger;

class FormsMessageService implements FormsMessageServiceInterface {
	/**
	 * @var array<string,mixed>|null
	 */
	private ?array $pending_values;

	/** @var callable(string):string */
	private $sanitize_key;

	/** @var callable():int */
	private $get_current_user_id;

	/** @var callable(string,mixed,int):mixed */
	private $set_transient;

	/** @var callable(string):mixed */
	private $get_transient;

	/** @var callable(string):mixed */
	private $delete_transient;

	/** @var callable():string */
	private $get_form_type_suffix;

	/**
	 * @param array<string,mixed>|null $pending_values
	 * @param callable(string):string $sanitize_key
	 * @param callable():int $get_current_user_id
	 * @param callable(string,mixed,int):mixed $set_transient
	 * @param callable(string):mixed $get_transient
	 * @param callable(string):mixed $delete_transient
	 * @param callable():string $get_form_type_suffix
	 */
	public function __construct(
		private FormMessageHandler $message_handler,
		private Logger $logger,
		private string $main_option,
		?array &$pending_values,
		callable $sanitize_key,
		callable $get_current_user_id,
		callable $set_transient,
		callable $get_transient,
		callable $delete_transient,
		callable $get_form_type_suffix
	) {
		$this->pending_values       = & $pending_values;
		$this->sanitize_key         = $sanitize_key;
		$this->get_current_user_id  = $get_current_user_id;
		$this->set_transient        = $set_transient;
		$this->get_transient        = $get_transient;
		$this->delete_transient     = $delete_transient;
		$this->get_form_type_suffix = $get_form_type_suffix;
	}

	public function take_messages(): array {
		$messages = $this->message_handler->get_all_messages();
		$this->message_handler->clear();
		return $messages ?? array(
			'warnings' => array(),
			'notices'  => array(),
		);
	}

	public function prepare_validation_messages(array $payload): void {
		$this->message_handler->clear();
		$this->message_handler->set_pending_values($payload);
		$this->pending_values = $payload;
	}

	public function process_validation_messages(RegisterOptions $options): array {
		$messages = $options->take_messages();
		$this->message_handler->set_messages($messages);

		$sanitizedValues = $options->get_options();
		if (!empty($sanitizedValues) && !empty($this->pending_values)) {
			foreach (array_keys($this->pending_values) as $key) {
				if (array_key_exists($key, $sanitizedValues)) {
					$this->pending_values[$key] = $sanitizedValues[$key];
				}
			}
			$this->message_handler->set_pending_values($this->pending_values);
		}

		return $messages;
	}

	public function has_validation_failures(): bool {
		return $this->message_handler->has_validation_failures();
	}

	public function clear_pending_validation(): void {
		$this->message_handler->set_pending_values(null);
		$this->pending_values = null;
	}

	public function log_validation_failure(string $message, array $context = array(), string $level = 'info'): void {
		if (!array_key_exists('warning_count', $context)) {
			$context['warning_count'] = $this->message_handler->get_warning_count();
		}

		switch ($level) {
			case 'warning':
				$this->logger->warning($message, $context);
				break;
			case 'error':
				$this->logger->error($message, $context);
				break;
			case 'debug':
				$this->logger->debug($message, $context);
				break;
			default:
				$this->logger->info($message, $context);
		}
	}

	public function log_validation_success(string $message, array $context = array(), string $level = 'debug'): void {
		switch ($level) {
			case 'info':
				$this->logger->info($message, $context);
				break;
			case 'warning':
				$this->logger->warning($message, $context);
				break;
			case 'error':
				$this->logger->error($message, $context);
				break;
			default:
				$this->logger->debug($message, $context);
		}
	}

	public function get_form_messages_transient_key(?int $user_id = null): string {
		if ($user_id === null) {
			$user_id = (int) ($this->get_current_user_id)();
		}
		$form_type = (string) ($this->get_form_type_suffix)();
		return 'ran_form_messages_' . $form_type . '_' . $this->main_option . '_' . $user_id;
	}

	public function persist_form_messages(array $messages, ?int $user_id = null): void {
		if (empty($messages)) {
			return;
		}

		$key  = $this->get_form_messages_transient_key($user_id);
		$data = array(
			'messages'       => $messages,
			'pending_values' => $this->pending_values,
		);
		($this->set_transient)($key, $data, 30);

		$this->logger->debug('forms.messages_persisted', array(
			'transient_key'        => $key,
			'field_count'          => count($messages),
			'pending_values_count' => $this->pending_values !== null ? count($this->pending_values) : 0,
		));
	}

	public function restore_form_messages(?int $user_id = null): bool {
		$key  = $this->get_form_messages_transient_key($user_id);
		$data = ($this->get_transient)($key);

		if (empty($data) || !is_array($data)) {
			return false;
		}

		($this->delete_transient)($key);

		if (isset($data['messages'])) {
			$messages       = $data['messages'];
			$pending_values = $data['pending_values'] ?? null;
		} else {
			$messages       = $data;
			$pending_values = null;
		}

		$this->message_handler->set_messages(is_array($messages) ? $messages : array());

		if ($pending_values !== null) {
			$this->message_handler->set_pending_values(is_array($pending_values) ? $pending_values : null);
			$this->pending_values = is_array($pending_values) ? $pending_values : null;
		}

		$this->logger->debug('forms.messages_restored', array(
			'transient_key'        => $key,
			'field_count'          => is_array($messages) ? count($messages) : 0,
			'pending_values_count' => is_array($pending_values) ? count($pending_values) : 0,
		));

		return true;
	}

	public function get_messages_for_field(string $field_id): array {
		$key      = ($this->sanitize_key)($field_id);
		$messages = $this->message_handler->get_messages_for_field($key);
		return $messages ?? array(
			'warnings' => array(),
			'notices'  => array(),
		);
	}
}
