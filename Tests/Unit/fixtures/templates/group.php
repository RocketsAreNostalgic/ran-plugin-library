<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<fieldset class="theme-group">' . ($context['content'] ?? '') . '</fieldset>',
	component_type: 'layout_wrapper'
);
