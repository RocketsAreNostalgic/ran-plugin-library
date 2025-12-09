<?php
/**
 * Fluent button link component definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Elements\ButtonLink;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

final class Builder extends ComponentBuilderBase {
	private string $url;
	private ?string $target    = null;
	private ?string $rel       = null;
	private ?string $icon_html = null;

	public function __construct(string $id, string $label, string $url) {
		parent::__construct($id, $label);
		$this->url = $url;
	}

	// attributes() and attribute() methods inherited from ComponentBuilderBase

	public function target(?string $target): static {
		$this->target = $target;
		return $this;
	}

	public function rel(?string $rel): static {
		$this->rel = $rel;
		return $this;
	}

	public function icon_html(?string $html): static {
		$this->icon_html = $html;
		return $this;
	}

	public function url(string $url): static {
		$this->url = $url;
		return $this;
	}


	protected function _build_component_context(): array {
		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Add required properties
		$context['label'] = $this->label;
		$context['url']   = $this->url;

		// Add optional properties using base class helpers
		$this->_add_if_not_empty($context, 'target', $this->target);
		$this->_add_if_not_empty($context, 'rel', $this->rel);
		$this->_add_if_not_empty($context, 'icon_html', $this->icon_html);

		return $context;
	}

	protected function _get_component(): string {
		return 'elements.button-link';
	}
}
