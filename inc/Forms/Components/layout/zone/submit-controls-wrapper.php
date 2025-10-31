<?php
/**
 * Submit Controls Wrapper Template
 *
 * Provides a generic layout wrapper for submission controls (e.g., primary and
 * secondary buttons) that can be reused across AdminSettings, FrontendForm, and
 * other form contexts.
 *
 * Expected $context keys:
 * - content: string  Rendered markup for the inner controls (required).
 * - zone_id: string  Optional identifier used for data attributes / testing.
 * - alignment: string Alignment of the controls (left|center|right|stretch). Defaults to 'right'.
 * - layout: string   Layout direction (inline|stacked). Defaults to 'inline'.
 * - class: string    Additional space-delimited classes to append to the wrapper.
 *
 * @package RanPluginLib\Forms\Components\Layout\Zone
 */

declare(strict_types=1);

use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentType;

// Prevent direct access.
if (!defined('ABSPATH')) {
	exit;
}

$content   = $context['content'] ?? '';
$zone_id   = isset($context['zone_id']) ? (string) $context['zone_id'] : '';
$alignment = isset($context['alignment']) ? strtolower((string) $context['alignment']) : 'right';
$layout    = isset($context['layout']) ? strtolower((string) $context['layout']) : 'inline';
$extra     = isset($context['class']) ? trim((string) $context['class']) : '';

$alignment = in_array($alignment, array('left', 'center', 'right', 'stretch'), true) ? $alignment : 'right';
$layout    = in_array($layout, array('inline', 'stacked'), true) ? $layout : 'inline';

$classes = array(
	'ran-zone-wrapper',
	'ran-zone-wrapper--submit-controls',
	"ran-zone-wrapper--align-{$alignment}",
	"ran-zone-wrapper--layout-{$layout}",
);

if ($extra !== '') {
	$classes[] = $extra;
}

$attribute_pairs = array('class' => implode(' ', array_map('sanitize_html_class', preg_split('/\s+/', implode(' ', $classes)))));

if ($zone_id !== '') {
	$attribute_pairs['data-zone-id'] = esc_attr($zone_id);
}

$attribute_markup = '';
foreach ($attribute_pairs as $name => $value) {
	if ($value === '') {
		continue;
	}
	$attribute_markup .= sprintf(' %s="%s"', esc_attr($name), esc_attr($value));
}

ob_start();
?>
<div<?php echo $attribute_markup; ?>>
	<?php if ($content !== ''): ?>
		<div class="ran-zone-wrapper__inner">
			<?php echo $content; // Content is expected to be pre-escaped.?>
		</div>
	<?php endif; ?>
</div>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean(),
	component_type: ComponentType::LayoutWrapper
);
