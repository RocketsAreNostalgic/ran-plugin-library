<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="complete.inline-group">' . ($context['content'] ?? '') . '</div>',
	component_type: 'layout_wrapper'
);
