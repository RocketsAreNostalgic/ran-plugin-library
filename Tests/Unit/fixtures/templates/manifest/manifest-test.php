<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="manifest-template">' . ($context['inner_html'] ?? '') . '</div>'
);
