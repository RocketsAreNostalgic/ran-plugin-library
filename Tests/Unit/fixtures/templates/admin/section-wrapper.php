<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="section-wrapper">' . ($context['content'] ?? '') . '</div>',
	component_type: 'layout_wrapper'
);
