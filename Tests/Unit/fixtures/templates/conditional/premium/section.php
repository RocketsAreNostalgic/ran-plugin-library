<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="premium-section">' . ($context['inner_html'] ?? '') . '</div>'
);
