<?php
/**
 * FieldsetBuilder: Fluent builder for semantic fieldset groups within a section.
 *
 * @template TRoot of BuilderRootInterface
 * @template TSection of SectionBuilder<TRoot>
 * @extends GroupBuilder<TRoot, TSection>
 * @implements FieldsetBuilderInterface<TRoot, TSection>
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use InvalidArgumentException;

class FieldsetBuilder extends GroupBuilder implements FieldsetBuilderInterface {
	private string $style;
	private bool $required;

	public function __construct(
		SectionBuilder $sectionBuilder,
		string $container_id,
		string $section_id,
		string $group_id,
		string $heading,
		?callable $description_cb,
		callable $updateFn,
		array $args = array()
	) {
		$this->style    = $this->normalize_style($args['style'] ?? 'bordered');
		$this->required = (bool) ($args['required'] ?? false);

		parent::__construct(
			$sectionBuilder,
			$container_id,
			$section_id,
			$group_id,
			$heading,
			$description_cb,
			$updateFn,
			$args
		);

		$this->template('fieldset-wrapper');
	}

	public function style(string $style): self {
		$this->_update_meta('style', $this->normalize_style($style));
		return $this;
	}

	public function required(bool $required = true): self {
		$this->_update_meta('required', $required);
		return $this;
	}

	/**
	 * Commit buffered data and return to the section builder.
	 *
	 * Alias for end_group() to support fieldset-specific fluent calls.
	 *
	 * @return SectionBuilder
	 */
	public function end_fieldset(): SectionBuilderInterface {
		return parent::end_group();
	}

	protected function _apply_meta_update(string $key, mixed $value): void {
		switch ($key) {
			case 'style':
				$this->style = $this->normalize_style($value);
				break;
			case 'required':
				$this->required = (bool) $value;
				break;
			default:
				parent::_apply_meta_update($key, $value);
				return;
		}

		parent::_apply_meta_update($key, $value);
	}

	protected function _build_update_payload(string $key, mixed $value): array {
		$payload                           = parent::_build_update_payload($key, $value);
		$payload['group_data']['style']    = $this->style;
		$payload['group_data']['required'] = $this->required;

		return $payload;
	}

	private function normalize_style(mixed $style): string {
		if (!is_string($style)) {
			throw new InvalidArgumentException('Fieldset style must be a string.');
		}

		$style = trim($style);
		if ($style === '') {
			throw new InvalidArgumentException('Fieldset style cannot be empty.');
		}

		return $style;
	}
}
