<?php
/**
 * Default admin page template
 *
 * Expected $context keys:
 * - heading: string - Page title
 * - description: string - Page description (optional)
 * - group: string - WordPress settings group (optional)
 * - content: string - Page content
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

/**
 * Default admin settings page view for tests.
 *
 * Mirrors the production root wrapper: consumes the prepared content payload and
 * returns a ComponentRenderResult for asset ingestion.
 */

if (!defined('ABSPATH')) {
	exit;
}

// Extract context variables
$heading        = $context['heading']     ?? 'Settings';
$description    = $context['description'] ?? '';
$settings_group = $context['group']       ?? ($context['settings_group'] ?? '');
$content        = $context['inner_html']  ?? '';

ob_start();
?>

<div class="wrap admin-settings-page">
    <?php if ($heading !== ''): ?>
        <h1><?php echo esc_html($heading); ?></h1>
    <?php endif; ?>

    <?php if ($description !== ''): ?>
        <p class="description"><?php echo esc_html($description); ?></p>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php
        if ($settings_group !== '' && function_exists('settings_fields')) {
        	settings_fields($settings_group);
        }
?>

        <div class="admin-page-content">
            <?php echo $content; ?>
        </div>

        <?php
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
	markup: (string) ob_get_clean()
);
return (string) ob_get_clean();
