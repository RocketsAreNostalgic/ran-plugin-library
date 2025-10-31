<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentType;

$markup = '<section class="test-section"></section>';

return new ComponentRenderResult(
	markup: $markup,
	component_type: ComponentType::LayoutWrapper
);