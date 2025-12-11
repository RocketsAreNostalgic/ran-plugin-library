<?php
/**
 * Example AdminSettings Page Template
 *
 * This template demonstrates co-located page template structure for AdminSettings.
 * It shows how to create a modern admin page layout that bypasses WordPress Settings API.
 *
 * @var string $page_slug The page slug
 * @var array $page_meta Page metadata
 * @var array $options Current option values
 * @var callable $render_sections Function to render sections
 * @var callable $render_submit Function to render submit button
 */

declare(strict_types=1);

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}
?>

<?php
ob_start();
?>
<div class="wrap example-admin-page">
    <h1 class="wp-heading-inline"><?php echo \esc_html($page_meta['heading'] ?? 'Settings'); ?></h1>

    <?php if (!empty($page_meta['description'])): ?>
        <p class="description"><?php echo \esc_html($page_meta['description']); ?></p>
    <?php endif; ?>

    <hr class="wp-header-end">

    <form method="post" action="options.php" class="example-settings-form">
        <?php if (\function_exists('settings_fields')) {
        	\settings_fields($group ?? 'default_group');
        } ?>

        <div class="example-settings-container">
            <div class="example-settings-main">
                <?php ($render_sections)(); ?>
            </div>

            <div class="example-settings-sidebar">
                <div class="example-settings-card">
                    <h3><?php echo \esc_html__('Quick Actions', 'textdomain'); ?></h3>
                    <p><?php echo \esc_html__('Save your settings to apply changes.', 'textdomain'); ?></p>
                    <?php ($render_submit)(); ?>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.example-admin-page {
    max-width: 1200px;
}

.example-settings-container {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 20px;
    margin-top: 20px;
}

.example-settings-main {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.example-settings-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.example-settings-card {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.example-settings-card h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .example-settings-container {
        grid-template-columns: 1fr;
    }
}
</style>
<?php
$markup = (string) ob_get_clean();

return new ComponentRenderResult(
	markup: $markup
);
