<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="complete-group">' . ($context['content'] ?? '') . '</div>',
	component_type: 'layout_wrapper'
);
