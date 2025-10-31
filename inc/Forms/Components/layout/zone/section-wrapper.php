<?php
/**
 * Shared Section Template
 *
 * Basic section container template for grouping related fields.
 * This template provides a minimal structure that can be enhanced
 * in the template architecture standardization sprint.
 * - content: string Section content
 *
 * @package RanPluginLib\Forms\Views\Shared
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentType;

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Extract context variables
$section_id  = $context['section_id']  ?? '';
$title       = $context['title']       ?? '';
$description = $context['description'] ?? '';
$content     = $context['content']     ?? '';

ob_start();
?>
<div class="form-section" data-section-id="<?php echo esc_attr($section_id); ?>">
	<?php if (!empty($title)): ?>
		<h3 class="form-section-title"><?php echo esc_html($title); ?></h3>
	<?php endif; ?>

	<?php if (!empty($description)): ?>
		<p class="form-section-description"><?php echo esc_html($description); ?></p>
	<?php endif; ?>

	<div class="form-section-content">
		<?php echo $content; // Section content is already escaped?>
	</div>
</div>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: ComponentType::LayoutWrapper
);
