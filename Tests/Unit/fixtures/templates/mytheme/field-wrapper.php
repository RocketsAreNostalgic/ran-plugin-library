<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="theme-field">' . ($context['inner_html'] ?? '') . '</div>'
);
