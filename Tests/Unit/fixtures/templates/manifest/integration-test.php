<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="integration-test">' . ($context['message'] ?? '') . '</div>',
	component_type: 'layout_wrapper'
);
