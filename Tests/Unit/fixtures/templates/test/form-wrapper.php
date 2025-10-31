<?php
/**
 * Test form wrapper template
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$markup = '<form class="test-form-wrapper">' . ($context['content'] ?? 'Test Form') . '</form>';

return new ComponentRenderResult(markup: $markup);
