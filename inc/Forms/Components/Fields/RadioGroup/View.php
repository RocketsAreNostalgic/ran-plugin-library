<?php
/**
 * Radio group field component.
 *
 * @var array{
 *     legend:?string,
 *     description:string,
 *     description_id:?string,
 *     attributes:string,
 *     options_html:array<int,string>,
 *     warnings:array<int,string>,
 *     notices:array<int,string>
 * } $context
 */

$legend        = isset($context['legend']) ? (string) $context['legend'] : '';
$description   = isset($context['description']) ? (string) $context['description'] : '';
$descriptionId = isset($context['description_id']) ? (string) $context['description_id'] : '';
$attributes    = isset($context['attributes']) ? (string) $context['attributes'] : '';
$options_html  = isset($context['options_html']) && is_array($context['options_html']) ? $context['options_html'] : array();
$warnings      = isset($context['warnings'])     && is_array($context['warnings']) ? $context['warnings'] : array();
$notices       = isset($context['notices'])      && is_array($context['notices']) ? $context['notices'] : array();

$fieldset_attr = trim($attributes);
$output_attr   = $fieldset_attr !== '' ? ' ' . $fieldset_attr : '';

ob_start();
?>
<fieldset<?php echo $output_attr; ?>>
	<?php if ($legend !== ''): ?>
		<legend><?php echo esc_html($legend); ?></legend>
	<?php endif; ?>

	<?php foreach ($options_html as $option_markup): ?>
		<?php echo $option_markup; ?>
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
<?php if ($description !== ''): ?>
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
