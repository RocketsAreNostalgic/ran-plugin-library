<?php
/**
 * Template for rendering a collection of already-rendered field rows (groups).
 *
 * @var array{
 *     rows: array<int, string>
 * } $context
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$rows = isset($context['rows']) && is_array($context['rows']) ? $context['rows'] : array();

return new ComponentRenderResult(
	markup: implode('', array_map('strval', $rows)),
	component_type: 'layout_wrapper'
);
