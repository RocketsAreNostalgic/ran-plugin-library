<?php
/**
 * Template for rendering a collection of already-rendered field rows (groups).
 *
 * @var array{
 *     rows: array<int, string>
 * } $context
 */

$rows = isset($context['rows']) && is_array($context['rows']) ? $context['rows'] : array();

return implode('', array_map('strval', $rows));
