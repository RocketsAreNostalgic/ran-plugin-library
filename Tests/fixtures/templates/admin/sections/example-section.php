<?php
/**
 * Example AdminSettings Section Template
 *
 * This template demonstrates co-located section template structure for AdminSettings.
 * It shows how to create a modern section container with card-like styling.
 *
 * @var string $section_id The section identifier
 * @var string $title Section title
 * @var string $description Section description (optional)
 * @var callable $render_fields Function to render section fields
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="example-section" id="section-<?php echo esc_attr($section_id); ?>">
    <div class="example-section-header">
        <h2 class="example-section-title"><?php echo esc_html($title); ?></h2>
        <?php if (!empty($description)): ?>
            <p class="example-section-description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
    </div>

    <div class="example-section-content">
        <?php ($render_fields)(); ?>
    </div>
</div>

<style>
.example-section {
    background: #f9f9f9;
    border: 1px solid #e1e1e1;
    border-radius: 6px;
    margin-bottom: 20px;
    overflow: hidden;
}

.example-section-header {
    background: #fff;
    padding: 20px;
    border-bottom: 1px solid #e1e1e1;
}

.example-section-title {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
    color: #1d2327;
}

.example-section-description {
    margin: 0;
    color: #646970;
    font-size: 14px;
    line-height: 1.5;
}

.example-section-content {
    padding: 20px;
}

.example-section-content .form-table {
    margin: 0;
    background: transparent;
}

.example-section-content .form-table th {
    padding-left: 0;
    width: 200px;
}

.example-section-content .form-table td {
    padding-right: 0;
}
</style>
