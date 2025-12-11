<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="original">' . ($context['inner_html'] ?? '') . '</div>'
);
