<?php
/**
 * Template for rendering a tables based single field row in user profile sections.
 *
 * @var array{
 *     label: string,
 *     inner_html: string,
 *     field_id?: string,
 *     description?: string,
 *     required?: bool,
 *     validation_warnings?: array<string>,
 *     display_notices?: array<string>,
 *     before?: string,
 *     after?: string,
 *     context?: array
 * } $context
 */
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

$inner_html = isset($context['inner_html']) ? (string) $context['inner_html'] : '';

if ($inner_html === '') {
	return new ComponentRenderResult(
		markup: ''
	);
}

$before = (string) ($context['before'] ?? '');
$after  = (string) ($context['after'] ?? '');

$description         = isset($context['description']) ? (string) $context['description'] : '';
$validation_warnings = isset($context['validation_warnings']) && is_array($context['validation_warnings']) ? $context['validation_warnings'] : array();
$display_notices     = isset($context['display_notices'])     && is_array($context['display_notices']) ? $context['display_notices'] : array();

$required   = isset($context['required']) && $context['required'];
$field_type = isset($context['field_type']) ? (string) $context['field_type'] : '';
$layout     = isset($context['layout']) ? (string) $context['layout'] : 'vertical';

$field_id = isset($context['field_id']) ? (string) $context['field_id'] : '';
$label    = isset($context['label']) ? (string) $context['label'] : '';

ob_start();
?>
<tr class="kplr-field<?php echo $required ? ' kplr-field--required' : ''; ?>" data-kplr-field-id="<?php echo esc_attr($field_id); ?>">
	<th class="kplr-field__label-cell" scope="row">
		<?php if ($label !== '') : ?>
			<label class="kplr-field__label"<?php echo $field_id !== '' ? ' for="' . esc_attr($field_id) . '"' : ''; ?>><?php echo esc_html($label); ?><?php echo $required ? '<span class="kplr-field__required">*</span>' : ''; ?></label>
		<?php endif; ?>
	</th>
	<td>
		<div class="kplr-field__input-cell">
			<?php echo $before; ?>
			<?php echo $inner_html; ?>
			<?php echo $after; ?>
			<?php if ($description !== '') : ?>
				<p class="kplr-field__description"><?php echo esc_html($description); ?></p>
			<?php endif; ?>
			<?php if (!empty($validation_warnings) || !empty($display_notices)) : ?>
				<div class="kplr-messages">
					<?php if (!empty($validation_warnings)) : ?>
						<div class="kplr-messages__warnings" role="alert">
							<?php foreach ($validation_warnings as $warning) : ?>
								<div class="kplr-messages__item kplr-messages__item--warning"><?php echo esc_html($warning); ?></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if (!empty($display_notices)) : ?>
						<div class="kplr-messages__notices">
							<?php foreach ($display_notices as $notice) : ?>
								<div class="kplr-messages__item kplr-messages__item--notice"><?php echo esc_html($notice); ?></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</td>
</tr>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean()
);
