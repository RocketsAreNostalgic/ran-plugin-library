<?php
/**
 * Shared Field Wrapper Template
 *
 * Basic field wrapper template for universal field rendering.
 * This template provides a minimal structure that can be enhanced
 * in the template architecture standardization sprint.
 *
 * @package RanPluginLib\Forms\Views\Shared
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Extract context variables
$field_id            = $context['field_id']            ?? '';
$label               = $context['label']               ?? '';
$component_html      = $context['component_html']      ?? '';
$validation_warnings = $context['validation_warnings'] ?? array();
$display_notices     = $context['display_notices']     ?? array();

ob_start();
?>
<div class="form-field-wrapper" data-field-id="<?php echo esc_attr($field_id); ?>">
	<?php if (!empty($label)): ?>
		<label for="<?php echo esc_attr($field_id); ?>" class="form-field-label">
			<?php echo esc_html($label); ?>
		</label>
	<?php endif; ?>

	<div class="form-field-content">
		<?php echo $component_html; // Component HTML is already escaped?>
	</div>

	<?php if (!empty($validation_warnings)): ?>
		<div class="form-field-warnings">
			<?php foreach ($validation_warnings as $warning): ?>
				<div class="form-warning"><?php echo esc_html($warning); ?></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if (!empty($display_notices)): ?>
		<div class="form-field-notices">
			<?php foreach ($display_notices as $notice): ?>
				<div class="form-notice"><?php echo esc_html($notice); ?></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
<?php
return (string) ob_get_clean();
