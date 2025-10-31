<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<main class="complete.grid-page">' . ($context['content'] ?? '') . '</main>',
	component_type: 'layout_wrapper'
);
