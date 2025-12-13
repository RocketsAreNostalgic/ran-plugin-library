<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<fieldset class="theme-group">' . ($context['inner_html'] ?? '') . '</fieldset>'
);
