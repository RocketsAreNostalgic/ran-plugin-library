<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="field-wrapper">' . ($context['inner_html'] ?? '') . '</div>'
);
