<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="complete.floating-label-wrapper">' . ($context['inner_html'] ?? '') . '</div>'
);
