<?php
/**
 * Template for rendering a single field row in user profile sections.
 *
 * @var array{
 *     label: string,
 *     content: string,
 *     field_id?: string,
 *     component_html?: string,
 *     validation_warnings?: array<string>,
 *     display_notices?: array<string>
 * } $context
 */

if (!isset($context['content']) || $context['content'] === '') {
	return '';
}

$label               = isset($context['label']) ? (string) $context['label'] : '';
$content             = (string) $context['content'];
$field_id            = isset($context['field_id']) ? (string) $context['field_id'] : '';
$validation_warnings = isset($context['validation_warnings']) && is_array($context['validation_warnings']) ? $context['validation_warnings'] : array();
$display_notices     = isset($context['display_notices'])     && is_array($context['display_notices']) ? $context['display_notices'] : array();

ob_start();
?>
<tr>
	<th scope="row">
		<?php if ($label !== '') : ?>
			<label<?php echo $field_id !== '' ? ' for="' . esc_attr($field_id) . '"' : ''; ?>><?php echo esc_html($label); ?></label>
		<?php endif; ?>
	</th>
	<td>
		<?php echo $content; ?>

		<?php if (!empty($validation_warnings)) : ?>
			<div class="form-field-warnings">
				<?php foreach ($validation_warnings as $warning) : ?>
					<p class="form-field-warning error"><?php echo esc_html($warning); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if (!empty($display_notices)) : ?>
			<div class="form-field-notices">
				<?php foreach ($display_notices as $notice) : ?>
					<p class="form-field-notice notice"><?php echo esc_html($notice); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</td>
</tr>
<?php
return (string) ob_get_clean();
