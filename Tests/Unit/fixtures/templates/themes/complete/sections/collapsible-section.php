<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<section class="complete.collapsible-section">' . ($context['inner_html'] ?? '') . '</section>'
);
