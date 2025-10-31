<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<fieldset class="complete.fieldset-group">' . ($context['content'] ?? '') . '</fieldset>',
	component_type: 'layout_wrapper'
);
