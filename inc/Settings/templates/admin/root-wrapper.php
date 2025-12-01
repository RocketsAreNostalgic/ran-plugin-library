<?php
/**
 * Default admin page template
 *
 * Expected $context keys:
 * - heading: string - Page title
 * - description: string - Page description (optional)
 * - settings_group: string - WordPress settings group (optional)
 * @package RanPluginLib\Settings\Admin\Templates
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

if (!defined('ABSPATH')) {
	exit;
}

// Extract context variables
$heading        = $context['heading']        ?? '';
$description    = $context['description']    ?? '';
$settings_group = $context['settings_group'] ?? '';
$inner_html     = $context['inner_html']     ?? '';

ob_start();
?>

<div class="wrap admin-settings-page">
    <?php if (!empty($heading)): ?>
        <h1><?php echo esc_html($heading); ?></h1>
    <?php endif; ?>

    <?php if (!empty($description)): ?>
        <p class="description"><?php echo esc_html($description); ?></p>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php
        if (!empty($settings_group)) {
        	// Mock settings_fields for testing
        	if (function_exists('settings_fields')) {
        		settings_fields($settings_group);
        	}
        }
?>

        <div class="admin-page-content">
            <?php echo $inner_html; ?>
        </div>

        <?php
// Mock submit_button for testing
if (function_exists('submit_button')) {
	submit_button();
} else {
	echo '<input type="submit" class="button-primary" value="Save Changes" />';
}
?>
    </form>
</div>

<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: 'layout_wrapper'
);
