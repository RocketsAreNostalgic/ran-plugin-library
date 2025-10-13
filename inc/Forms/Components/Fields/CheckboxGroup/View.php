<?php
/**
 * Checkbox group component template.
 *
 * @var array{
 *     attributes:string,
 *     options_html:array<int,string>,
 *     description:string,
 *     description_id:?string,
 *     legend:?string,
 *     warnings:array<int,string>,
 *     notices:array<int,string>
 * } $context
 */

$attributes    = isset($context['attributes']) ? trim((string) $context['attributes']) : '';
$legend        = isset($context['legend']) ? (string) $context['legend'] : '';
$optionsHtml   = isset($context['options_html']) && is_array($context['options_html']) ? $context['options_html'] : array();
$description   = isset($context['description']) ? (string) $context['description'] : '';
$descriptionId = isset($context['description_id']) ? (string) $context['description_id'] : '';
$warnings      = isset($context['warnings']) && is_array($context['warnings']) ? $context['warnings'] : array();
$notices       = isset($context['notices'])  && is_array($context['notices']) ? $context['notices'] : array();

ob_start();
?>
<fieldset class="checkbox-group"<?php echo $attributes !== '' ? ' ' . $attributes : ''; ?>>
	<?php if ($legend !== '') : ?>
		<legend><?php echo esc_html($legend); ?></legend>
	<?php endif; ?>
	<?php foreach ($optionsHtml as $optionMarkup) : ?>
		<?php echo $optionMarkup; ?>
	<?php endforeach; ?>
</fieldset>
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
<?php if ($description !== '') : ?>
	<p class="description"<?php echo $descriptionId !== '' ? ' id="' . esc_attr($descriptionId) . '"' : ''; ?>><?php echo esc_html($description); ?></p>
<?php endif; ?>
<?php
return array(
	'markup'         => (string) ob_get_clean(),
	'script'         => null,
	'style'          => null,
	'requires_media' => false,
	'repeatable'     => false,
	'context_schema' => array(
	    'required' => array('attributes', 'options_html'),
	    'optional' => array('legend', 'description', 'description_id', 'warnings', 'notices'),
	    'defaults' => array(
	        'legend'         => '',
	        'description'    => '',
	        'description_id' => '',
	        'warnings'       => array(),
	        'notices'        => array(),
	    ),
	),
);
