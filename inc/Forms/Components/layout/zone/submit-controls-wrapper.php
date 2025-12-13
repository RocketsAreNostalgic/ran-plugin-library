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

// Prevent direct access.
if (!defined('ABSPATH')) {
	exit;
}

$inner_html = $context['inner_html'] ?? '';
$zone_id    = isset($context['zone_id']) ? (string) $context['zone_id'] : '';
$extra      = isset($context['class']) ? trim((string) $context['class']) : '';
$before     = isset($context['before']) ? (string) $context['before'] : '';
$after      = isset($context['after'])  ? (string) $context['after']  : '';

$classes = array(
	'ran-zone-wrapper',
	'ran-zone-wrapper--submit-controls',
);

if ($extra !== '') {
	$classes[] = $extra;
}

$attribute_pairs = array('class' => implode(' ', array_map('sanitize_html_class', preg_split('/\s+/', implode(' ', $classes)))));

if ($zone_id !== '') {
	$attribute_pairs['data-zone'] = esc_attr($zone_id);
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
	<?php if ($inner_html !== ''): ?>
		<div class="ran-zone-wrapper__inner">
			<?php if ($before !== ''): ?>
				<?php echo $before; // Hook output should already be escaped.?>
			<?php endif; ?>

			<?php echo $inner_html; // Inner HTML is expected to be pre-escaped.?>

			<?php if ($after !== ''): ?>
				<?php echo $after; // Hook output should already be escaped.?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean()
);
