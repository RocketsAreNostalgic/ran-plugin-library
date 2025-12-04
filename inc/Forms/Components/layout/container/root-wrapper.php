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
$form_id       = $context['form_id']    ?? '';
$title         = $context['title']      ?? '';
$heading       = $context['heading']    ?? $title;
$inner_html    = $context['inner_html'] ?? '';
$before        = (string) ($context['before'] ?? '');
$after         = (string) ($context['after'] ?? '');
$renderSubmit  = $context['render_submit'] ?? null;
$form_messages = $context['form_messages'] ?? array();
$group         = $context['group']      ?? '';  // WordPress Settings API group

ob_start();
?>
<div class="wrap">
	<?php if (!empty($heading)): ?>
		<h1><?php echo esc_html($heading); ?></h1>
	<?php endif; ?>

	<?php if (!empty($form_messages)): ?>
		<div class="kplr-messages">
			<?php foreach ($form_messages as $message_type => $messages) : ?>
				<?php if (!empty($messages)) : ?>
					<div class="kplr-messages__<?php echo esc_attr($message_type); ?>">
						<?php foreach ($messages as $message) : ?>
							<div class="kplr-messages__item kplr-messages__item--<?php echo esc_attr($message_type); ?>">
								<?php echo esc_html($message); ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php" class="kplr-form" data-kplr-form-id="<?php echo esc_attr($form_id); ?>">
		<?php
		// Output WordPress Settings API hidden fields (nonce, option_page, action)
		if (!empty($group) && function_exists('settings_fields')) {
			settings_fields($group);
		}
		?>

		<div class="kplr-form__content">
			<?php if ($before !== ''): ?>
				<?php echo $before; // Hook output should already be escaped.?>
			<?php endif; ?>

			<?php echo $inner_html; // Form inner HTML is already escaped?>

			<?php if ($after !== ''): ?>
				<?php echo $after; // Hook output should already be escaped.?>
			<?php endif; ?>
		</div>

		<?php if (is_callable($renderSubmit)): ?>
			<div class="kplr-form__submit">
				<?php echo (string) $renderSubmit(); ?>
			</div>
		<?php endif; ?>
	</form>
</div>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: ComponentType::LayoutWrapper
);
