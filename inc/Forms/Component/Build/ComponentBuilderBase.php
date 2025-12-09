<?php
/**
 * Shared base class for fluent form component builders.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Build;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Util\TranslationService;

abstract class ComponentBuilderBase implements ComponentBuilderDefinitionInterface {
	protected string $id;
	protected string $label;
	protected ?int $order = null;
	/** @var array<string,string> */
	protected array $attributes               = array();
	protected ?string $description            = null;
	protected ?TranslationService $translator = null;

	/**
	 * Provide default manifest defaults for builders that don't override them.
	 *
	 * @return array<string,mixed>
	 */
	public static function manifest_defaults(): array {
		return array();
	}

	public function __construct(string $id, string $label, ?TranslationService $translator = null) {
		$this->id         = $id;
		$this->label      = $label;
		$this->translator = $translator;
	}

	/**
	 * Orders the component relative to its siblings.
	 *
	 * @param int|null $order
	 *
	 * @return self
	 */
	public function order(?int $order): static {
		$this->order = $order;
		return $this;
	}

	/**
	 * Sets multiple attributes for the component.
	 *
	 * @param array<string,string> $attributes
	 * @return self
	 */
	public function attributes(array $attributes): static {
		foreach ($attributes as $key => $value) {
			$this->attribute((string) $key, (string) $value);
		}
		return $this;
	}

	/**
	 * Sets a single attribute for the component.
	 *
	 * @param string $key
	 * @param string $value
	 * @return self
	 */
	public function attribute(string $key, string $value): static {
		$this->attributes[$key] = (string) $value;
		return $this;
	}

	/**
	 * Sets the description/help text for the component.
	 *
	 * @param string|null $description
	 * @return self
	 */
	public function description(?string $description): static {
		$this->description = $description;
		return $this;
	}

	/**
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * @return int|null
	 */
	public function get_order(): ?int {
		return $this->order;
	}

	/**
	 * @return array<string,string>
	 */
	public function get_attributes(): array {
		return $this->attributes;
	}

	/**
	 * @return string|null
	 */
	public function get_description(): ?string {
		return $this->description;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		$order = $this->order;
		if ($order === null) {
			$order = 0;
		}

		return $this->_after_to_array(array(
			'id'                => $this->id,
			'label'             => $this->label,
			'order'             => (int) $order,
			'component'         => $this->_get_component(),
			'component_context' => $this->_build_component_context(),
		));
	}

	/**
	 * Create a translation service for builders.
	 *
	 * @param string $textDomain The WordPress text domain to use.
	 *
	 * @return TranslationService
	 */
	public static function create_translation_service(string $textDomain = 'ran-plugin-lib'): TranslationService {
		return TranslationService::for_domain('forms/builder', $textDomain);
	}

	/**
	 * Get the translation service for this builder.
	 *
	 * @return TranslationService
	 */
	protected function _get_translator(): TranslationService {
		if ($this->translator === null) {
			$this->translator = self::create_translation_service();
		}
		return $this->translator;
	}

	/**
	 * Translate a message using the translation service.
	 *
	 * @param string $message The message to translate.
	 * @param string $context Optional context for the translation.
	 *
	 * @return string The translated message.
	 */
	protected function _translate(string $message, string $context = ''): string {
		return $this->_get_translator()->translate($message, $context);
	}

	/**
	 * Allow subclasses to modify the serialized payload.
	 *
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	protected function _after_to_array(array $payload): array {
		return $payload;
	}

	/**
	 * Identify the component slug rendered by this builder.
	 *
	 * @return string
	 */
	abstract protected function _get_component(): string;

	/**
	 * Produce contextual data for this component.
	 *
	 * @return array<string,mixed>
	 */
	abstract protected function _build_component_context(): array;

	/**
	 * Build base context that most components need.
	 * Child classes can call this and extend the returned array.
	 *
	 * @return array<string,mixed>
	 */
	protected function _build_base_context(): array {
		$context = array(
			'attributes' => $this->attributes,
		);

		if ($this->description !== null) {
			$context['description'] = $this->description;
		}

		return $context;
	}

	/**
	 * Add a value to context only if it's not null/empty.
	 *
	 * @param array<string,mixed> $context
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	protected function _add_if_not_empty(array &$context, string $key, mixed $value): void {
		if ($value !== null && $value !== '' && $value !== array()) {
			$context[$key] = $value;
		}
	}

	/**
	 * Add a boolean value to context only if it's true.
	 *
	 * @param array<string,mixed> $context
	 * @param string $key
	 * @param bool $value
	 * @return void
	 */
	protected function _add_if_true(array &$context, string $key, bool $value): void {
		if ($value) {
			$context[$key] = true;
		}
	}
}
