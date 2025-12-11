<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="override">' . ($context['inner_html'] ?? '') . '</div>'
);
