<?php
/**
 * Shared Form Wrapper Template
 *
 * Basic form container template for complete form control.
 * This template provides a minimal structure that can be enhanced
 * in the template architecture standardization sprint.
 * - notices: array<int,string> - Optional notices to display above the form.
 *
 * @package RanPluginLib\Forms\Views\Form
 */

use Ran\PluginLib\Forms\Component\ComponentType;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Extract context variables
$form_id       = $context['form_id'] ?? '';
$title         = $context['title']   ?? '';
$content       = $context['content'] ?? '';
$before        = isset($context['before']) ? (string) $context['before'] : '';
$after         = isset($context['after'])  ? (string) $context['after']  : '';
$renderSubmit  = $context['render_submit'] ?? null;
$form_messages = $context['form_messages'] ?? array();

ob_start();
?>
<div class="form-wrapper" data-form-id="<?php echo esc_attr($form_id); ?>">
	<?php if (!empty($title)): ?>
		<h2 class="form-title"><?php echo esc_html($title); ?></h2>
	<?php endif; ?>

	<?php if (!empty($form_messages)): ?>
		<div class="form-messages">
			<?php foreach ($form_messages as $message_type => $messages): ?>
				<?php if (!empty($messages)): ?>
					<div class="form-messages-<?php echo esc_attr($message_type); ?>">
						<?php foreach ($messages as $message): ?>
							<div class="form-message form-message-<?php echo esc_attr($message_type); ?>">
								<?php echo esc_html($message); ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="form-content">
		<?php if ($before !== ''): ?>
			<?php echo $before; // Hook output should already be escaped.?>
		<?php endif; ?>

		<?php echo $content; // Form content is already escaped?>

		<?php if ($after !== ''): ?>
			<?php echo $after; // Hook output should already be escaped.?>
		<?php endif; ?>
	</div>

	<?php if (is_callable($renderSubmit)): ?>
		<div class="form-submit-controls">
			<?php echo (string) $renderSubmit(); ?>
		</div>
	<?php endif; ?>
</div>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: ComponentType::LayoutWrapper
);
