<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<section class="complete.collapsible-section">' . ($context['content'] ?? '') . '</section>',
	component_type: 'layout_wrapper'
);
