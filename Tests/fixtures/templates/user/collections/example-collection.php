<?php
/**
 * Example UserSettings Collection Template
 *
 * This template demonstrates co-located collection template structure for UserSettings.
 * It shows how to create a collection wrapper optimized for WordPress profile page table constraints.
 *
 * @var string $collection_slug The collection identifier
 * @var string $title Collection title
 * @var string $description Collection description (optional)
 * @var callable $render_sections Function to render collection sections
 */

declare(strict_types=1);

use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentType;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

ob_start();
?>

<div class="example-user-collection" id="collection-<?php echo \esc_attr($collection_slug); ?>">
    <?php if (!empty($title)): ?>
        <h2 class="example-collection-title"><?php echo \esc_html($title); ?></h2>
    <?php endif; ?>

    <?php if (!empty($description)): ?>
        <p class="example-collection-description"><?php echo \esc_html($description); ?></p>
    <?php endif; ?>

    <div class="example-collection-content">
        <?php ($render_sections)(); ?>
    </div>
</div>

<style>
.example-user-collection {
    margin-bottom: 20px;
}

.example-collection-title {
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
    margin: 0 0 10px 0;
    padding: 0;
    border-bottom: 1px solid #e1e1e1;
    padding-bottom: 8px;
}

.example-collection-description {
    margin: 0 0 15px 0;
    color: #646970;
    font-size: 14px;
    line-height: 1.5;
}

.example-collection-content {
    /* Inherit table styling from WordPress profile page */
}

/* Ensure compatibility with WordPress profile page table */
.form-table .example-user-collection {
    margin: 0;
}

.form-table .example-collection-title {
    font-size: 14px;
    margin-bottom: 5px;
}

.form-table .example-collection-description {
    font-size: 13px;
    margin-bottom: 10px;
}
</style>

<?php
$markup = (string) ob_get_clean();

return new ComponentRenderResult(
	markup: $markup,
	component_type: ComponentType::LayoutWrapper
);
