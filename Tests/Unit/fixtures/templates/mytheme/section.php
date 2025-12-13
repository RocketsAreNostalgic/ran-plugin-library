<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<section class="theme-section">' . ($context['inner_html'] ?? '') . '</section>'
);
