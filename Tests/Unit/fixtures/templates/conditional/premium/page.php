<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="premium-page">' . ($context['content'] ?? '') . '</div>',
	component_type: 'layout_wrapper'
);
