<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<main class="complete.sidebar-page">' . ($context['inner_html'] ?? '') . '</main>'
);
