<?php
/**
 * Example UserSettings Field Wrapper Template
 *
 * This template demonstrates co-located field wrapper template structure for UserSettings.
 * It shows how to create a field wrapper optimized for WordPress profile page table constraints.
 *
 * @var string $field_id The field identifier
 * @var string $label Field label
 * @var string $inner_html Rendered component HTML
 * @var array $validation_warnings Validation warning messages
 * @var array $display_notices Display notice messages
 * @var string $description Field description/help text
 * @var bool $required Whether field is required
 * @var array $context Additional field context
 */

declare(strict_types=1);

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

$has_warnings = !empty($validation_warnings);
$has_notices  = !empty($display_notices);

ob_start();
?>

<!-- This template is designed to work within WordPress profile page table structure -->
<tr class="example-user-field-row <?php echo $has_warnings ? 'has-warnings' : ''; ?>" data-field-id="<?php echo \esc_attr($field_id); ?>">
    <th scope="row" class="example-user-field-label">
        <label for="<?php echo \esc_attr($field_id); ?>">
            <?php echo \esc_html($label); ?>
            <?php if ($required): ?>
                <span class="example-required-indicator" aria-label="<?php echo \esc_attr__('Required field', 'textdomain'); ?>">*</span>
            <?php endif; ?>
        </label>

        <?php if (!empty($description)): ?>
            <p class="example-user-field-description">
                <?php echo \esc_html($description); ?>
            </p>
        <?php endif; ?>
    </th>

    <td class="example-user-field-input">
        <div class="example-user-input-container">
            <?php echo $inner_html; ?>

            <?php if ($has_warnings): ?>
                <div class="example-user-field-warnings" role="alert">
                    <?php foreach ($validation_warnings as $warning): ?>
                        <div class="example-user-warning-message">
                            <span class="example-warning-icon" aria-hidden="true">⚠</span>
                            <?php echo \esc_html($warning); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($has_notices): ?>
                <div class="example-user-field-notices">
                    <?php foreach ($display_notices as $notice): ?>
                        <div class="example-user-notice-message">
                            <span class="example-notice-icon" aria-hidden="true">ℹ</span>
                            <?php echo \esc_html($notice); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </td>
</tr>

<style>
.example-user-field-row th {
    vertical-align: top;
    padding-top: 15px;
}

.example-user-field-label label {
    font-weight: 600;
    color: #1d2327;
}

.example-required-indicator {
    color: #d63638;
    font-weight: bold;
    margin-left: 3px;
}

.example-user-field-description {
    margin: 5px 0 0 0;
    font-size: 13px;
    color: #646970;
    line-height: 1.4;
    font-weight: normal;
}

.example-user-input-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-width: 400px;
}

.example-user-field-warnings {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.example-user-warning-message {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: #fcf2f2;
    border: 1px solid #f0a0a0;
    border-radius: 3px;
    font-size: 12px;
    color: #8a2424;
}

.example-warning-icon {
    color: #d63638;
    font-size: 12px;
    flex-shrink: 0;
}

.example-user-field-notices {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.example-user-notice-message {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: #f0f6fc;
    border: 1px solid #a0c4e0;
    border-radius: 3px;
    font-size: 12px;
    color: #0a4b78;
}

.example-notice-icon {
    color: #0073aa;
    font-size: 12px;
    flex-shrink: 0;
}

.example-user-field-row.has-warnings th label {
    color: #8a2424;
}

/* Ensure inputs fit well within table constraints */
.example-user-input-container input[type="text"],
.example-user-input-container input[type="email"],
.example-user-input-container input[type="url"],
.example-user-input-container input[type="password"],
.example-user-input-container select,
.example-user-input-container textarea {
    width: 100%;
    max-width: 400px;
}

.example-user-input-container textarea {
    min-height: 60px;
    resize: vertical;
}

/* Focus styles for accessibility */
.example-user-input-container input:focus,
.example-user-input-container select:focus,
.example-user-input-container textarea:focus {
    outline: 2px solid #0073aa;
    outline-offset: 1px;
}

/* Mobile responsiveness within table constraints */
@media (max-width: 768px) {
    .example-user-input-container {
        max-width: none;
    }

    .example-user-input-container input,
    .example-user-input-container select,
    .example-user-input-container textarea {
        max-width: none;
    }
}
</style>

<?php
$markup = (string) ob_get_clean();

return new ComponentRenderResult(
	markup: $markup
);
