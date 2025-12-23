<?php
/**
 * Default metabox wrapper template.
 *
 * Expected $context keys:
 * - inner_html: string
 * - meta_key: string
 * - nonce_action: string
 * - nonce_name: string
 *
 * @package Ran\PluginLib\Metaboxes\Templates
 */

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

if (!defined('ABSPATH')) {
	exit;
}

$inner_html    = (string) ($context['inner_html'] ?? '');
$meta_key      = (string) ($context['meta_key'] ?? '');
$nonce_action  = (string) ($context['nonce_action'] ?? '');
$nonce_name    = (string) ($context['nonce_name'] ?? '');
$style         = trim((string) ($context['style'] ?? ''));
$messagesByKey = $context['messages_by_field'] ?? array();

ob_start();
?>

<div class="kplr-form kplr-form--metabox<?php echo $style !== '' ? ' ' . esc_attr($style) : ''; ?>" data-kplr-form-id="<?php echo esc_attr($meta_key); ?>">
	<?php
	if ($nonce_action !== '' && $nonce_name !== '') {
		if (function_exists('wp_nonce_field')) {
			echo wp_nonce_field($nonce_action, $nonce_name, true, false);
		}
	}
?>

	<?php echo $inner_html; ?>
</div>

<script>
(function() {
	var wrapper = document.querySelector('[data-kplr-form-id="<?php echo esc_js($meta_key); ?>"]');
	if (!wrapper) {
		return;
	}
	if (!wrapper.querySelector('input[type="file"]')) {
		return;
	}
	var form = document.querySelector('form#post');
	if (form && form.getAttribute('enctype') !== 'multipart/form-data') {
		form.setAttribute('enctype', 'multipart/form-data');
	}
})();
</script>

<?php
return new ComponentRenderResult(
	markup: (string) ob_get_clean()
);
