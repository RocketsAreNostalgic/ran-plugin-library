<?php

declare(strict_types=1);

use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentType;

$componentHtml = isset($component_html) ? (string) $component_html : '';

return new ComponentRenderResult(
	'<div class="test-field-wrapper">' . $componentHtml . '</div>',
	component_type: ComponentType::LayoutWrapper
);
