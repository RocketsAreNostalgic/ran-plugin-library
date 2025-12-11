<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="custom-page">' . ($context['title'] ?? 'Default Title') . '</div>'
);
