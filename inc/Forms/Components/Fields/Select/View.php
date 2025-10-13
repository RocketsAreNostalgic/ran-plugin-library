<?php
/**
 * Select field component.
 *
 * @var array{
 *     select_attributes:string,
 *     options_html:array<int,string>,
 *     description:string,
 *     description_id:?string,
 *     warnings:array<int,string>,
 *     notices:array<int,string>
 * } $context
 */

$selectAttributes = isset($context['select_attributes']) ? trim((string) $context['select_attributes']) : '';
$optionsHtml      = isset($context['options_html']) && is_array($context['options_html']) ? $context['options_html'] : array();
$description      = isset($context['description']) ? (string) $context['description'] : '';
$descriptionId    = isset($context['description_id']) ? (string) $context['description_id'] : '';
$warnings         = isset($context['warnings']) && is_array($context['warnings']) ? $context['warnings'] : array();
$notices          = isset($context['notices'])  && is_array($context['notices']) ? $context['notices'] : array();

ob_start();
?>
<select<?php echo $selectAttributes !== '' ? ' ' . $selectAttributes : ''; ?>>
	<?php foreach ($optionsHtml as $optionMarkup): ?>
		<?php echo $optionMarkup; ?>
	<?php endforeach; ?>
</select>
<?php if (!empty($warnings)) : ?>
	<?php foreach ($warnings as $warning) : ?>
		<p class="form-message form-message--warning"><?php echo esc_html($warning); ?></p>
	<?php endforeach; ?>
<?php endif; ?>
<?php if (!empty($notices)) : ?>
	<?php foreach ($notices as $notice) : ?>
		<p class="form-message form-message--notice"><?php echo esc_html($notice); ?></p>
	<?php endforeach; ?>
<?php endif; ?>
<?php if ($description !== ''): ?>
	<p class="description"<?php echo $descriptionId !== '' ? ' id="' . esc_attr($descriptionId) . '"' : ''; ?>><?php echo esc_html($description); ?></p>
<?php endif; ?>
<?php
return array(
	'markup'         => (string) ob_get_clean(),
	'script'         => null,
	'style'          => null,
	'requires_media' => false,
	'repeatable'     => true,
	'context_schema' => array(
	    'required' => array('select_attributes', 'options_html'),
	    'optional' => array('description', 'description_id', 'warnings', 'notices'),
	    'defaults' => array(
	        'description'    => '',
	        'description_id' => '',
	        'warnings'       => array(),
	        'notices'        => array(),
	    ),
	),
);
