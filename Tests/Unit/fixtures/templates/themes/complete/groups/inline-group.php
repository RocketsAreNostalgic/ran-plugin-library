<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="complete.inline-group">' . ($context['inner_html'] ?? '') . '</div>'
);
