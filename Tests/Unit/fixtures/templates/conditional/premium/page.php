<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="premium-page">' . ($context['inner_html'] ?? '') . '</div>'
);
