<?php
/**
 * Template for rendering a tables based single field row in user profile sections.
 *
 * @var array{
 *     label: string,
 *     content: string,
 *     field_id?: string,
 *     component_html?: string,
 *     description?: string,
 *     required?: bool,
 *     validation_warnings?: array<string>,
 *     display_notices?: array<string>,
 *     before?: string,
 *     after?: string,
 *     context?: array
 * } $context
 */
use Ran\PluginLib\Forms\Component\ComponentType;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Support both 'content' and 'component_html' keys for compatibility
$content = '';
if (isset($context['content']) && $context['content'] !== '') {
	$content = (string) $context['content'];
} elseif (isset($context['component_html']) && $context['component_html'] !== '') {
	$content = (string) $context['component_html'];
}

if ($content === '') {
	return new ComponentRenderResult(
		markup: '',
		component_type: ComponentType::LayoutWrapper
	);
}

$before = (string) ($context['before'] ?? '');
$after  = (string) ($context['after'] ?? '');

$description         = isset($context['description']) ? (string) $context['description'] : '';
$validation_warnings = isset($context['validation_warnings']) && is_array($context['validation_warnings']) ? $context['validation_warnings'] : array();
$display_notices     = isset($context['display_notices'])     && is_array($context['display_notices']) ? $context['display_notices'] : array();

$required            = isset($context['required']) && $context['required'];
$field_type          = isset($context['field_type']) ? (string) $context['field_type'] : '';
$layout              = isset($context['layout']) ? (string) $context['layout'] : 'vertical';

$field_id            = isset($context['field_id']) ? (string) $context['field_id'] : '';
$label               = isset($context['label']) ? (string) $context['label'] : '';

ob_start();
?>
<tr>
	<th scope="row">
		<?php if ($label !== '') : ?>
			<label<?php echo $field_id !== '' ? ' for="' . esc_attr($field_id) . '"' : ''; ?>><?php echo esc_html($label); ?><?php echo $required ? '<span class="required">*</span>' : ''; ?></label>
		<?php endif; ?>
	</th>
	<td>
		<?php echo $before; ?>
		<?php echo $content; ?>
		<?php echo $after; ?>
		<?php if ($description !== '') : ?>
			<p class="description"><?php echo esc_html($description); ?></p>
		<?php endif; ?>
		 <?php if (!empty($description) && $layout === 'vertical'): ?>
            <div class="field-wrapper__description">
                <?php echo esc_html($description); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($validation_warnings) || !empty($display_notices)): ?>
            <div class="field-wrapper__messages">
                <?php if (!empty($validation_warnings)): ?>
                    <div class="form-field-warnings" role="alert">
                        <?php foreach ($validation_warnings as $warning): ?>
                            <div class="form-field-warning error">
                                <?php echo esc_html($warning); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($display_notices)): ?>
                    <div class="form-field-notices">
                        <?php foreach ($display_notices as $notice): ?>
                            <div class="field-wrapper__notice">
                                <?php echo esc_html($notice); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
	</td>
</tr>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: 'layout_wrapper'
);
