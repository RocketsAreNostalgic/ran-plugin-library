<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<section class="modern-section">' . ($context['inner_html'] ?? '') . '</section>'
);
