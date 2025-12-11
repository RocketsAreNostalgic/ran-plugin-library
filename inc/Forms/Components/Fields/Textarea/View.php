<?php
/**
 * Textarea component template.
 * Clean component focused solely on form control markup.
 *
 * @var array{
 *     textarea_attributes:string,
 *     value:string
 * } $context
 */

$textareaAttributes = isset($context['textarea_attributes']) ? trim((string) $context['textarea_attributes']) : '';
$value              = isset($context['value']) ? (string) $context['value'] : '';

ob_start();
?>
<textarea<?php echo $textareaAttributes !== '' ? ' ' . $textareaAttributes : ''; ?>><?php echo esc_textarea($value); ?></textarea>
<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	script: null,
	style: null,
	requires_media: false,
	repeatable: true,
	context_schema: array(
	    'required' => array('textarea_attributes'),
	    'optional' => array('value'),
	    'defaults' => array(
	        'value' => '',
	    ),
	)
);
