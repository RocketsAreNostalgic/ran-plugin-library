<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<section class="modern-section">' . ($context['content'] ?? '') . '</section>',
	component_type: 'layout_wrapper'
);
