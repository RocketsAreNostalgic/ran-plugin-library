<?php
/**
 * Input component template.
 * Clean component focused solely on form control markup.
 *
 * @var array{
 *     input_attributes:string,
 * 	   input_type:string // e.g., 'text', 'number', 'email', 'password', etc.
 * 	   repeatable:?bool
 * } $context
 */

$inputAttributes = isset($context['input_attributes']) ? trim((string) $context['input_attributes']) : '';

ob_start();
?>
<input<?php echo $inputAttributes !== '' ? ' ' . $inputAttributes : ''; ?>>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	context_schema: array(
	    'required' => array('input_attributes', 'input_type'),
	    'optional' => array('repeatable'),
	    'defaults' => array(),
	)
);
