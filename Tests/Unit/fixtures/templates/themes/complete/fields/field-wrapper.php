<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="complete-field-wrapper">' . ($context['inner_html'] ?? '') . '</div>'
);
