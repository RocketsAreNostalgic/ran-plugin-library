<?php
/**
 * Trait providing immediate update helper for settings builders.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

trait BuilderImmediateUpdateTrait {
	/**
	 * Persist a settings meta value immediately via the update callback.
	 *
	 * @param string $key   Meta key being updated.
	 * @param mixed  $value New value for the meta key.
	 */
	protected function _update_meta(string $key, mixed $value): void {
		$this->_apply_meta_update($key, $value);
		($this->_get_update_callback())($this->_get_update_event_name(), $this->_build_update_payload($key, $value));
	}

	/**
	 * Persist the updated value locally if needed.
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	abstract protected function _apply_meta_update(string $key, mixed $value): void;

	/**
	 * Return the update callback (typically $this->updateFn).
	 */
	abstract protected function _get_update_callback(): callable;

	/**
	 * Return the event name emitted through the update callback.
	 */
	abstract protected function _get_update_event_name(): string;

	/**
	 * Build the payload array sent to the update callback.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return array<string,mixed>
	 */
	abstract protected function _build_update_payload(string $key, mixed $value): array;
}
