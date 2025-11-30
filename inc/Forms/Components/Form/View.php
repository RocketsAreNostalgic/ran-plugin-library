<?php
/**
 * Minimal front-end form wrapper.
 *
 * Expected $context keys:
 * - action: string Form action URL (required).
 * - method: string HTTP method (defaults to POST).
 * - attributes: array<string,string|int|bool> Optional HTML attributes.
 * - has_files: bool Set to true to add enctype="multipart/form-data".
 * - nonce_action: string|null Optional nonce action for wp_nonce_field().
 * - nonce_field: string Optional nonce field name (defaults to _wpnonce when nonce_action is provided).
 * - children: string Form inner HTML (required).
 * - errors: array<int,string> Optional error messages to surface above the form.
 * - notices: array<int,string> Optional notices to display above the form.
 */

declare(strict_types=1);

$translator = \Ran\PluginLib\Util\TranslationService::for_domain('forms/view');

$action     = isset($context['action']) ? (string) $context['action'] : '';
$method     = isset($context['method']) ? strtoupper((string) $context['method']) : 'POST';
$attributes = isset($context['attributes']) && is_array($context['attributes']) ? $context['attributes'] : array();
$hasFiles   = !empty($context['has_files']);
$children   = isset($context['children']) ? (string) $context['children'] : '';
$errors     = isset($context['errors'])  && is_array($context['errors']) ? $context['errors'] : array();
$notices    = isset($context['notices']) && is_array($context['notices']) ? $context['notices'] : array();

if ($action === '') {
	$fallbackAction    = $_SERVER['REQUEST_URI'] ?? '/';
	$sanitizedFallback = filter_var((string) $fallbackAction, FILTER_SANITIZE_URL) ?: '';
	$action            = $sanitizedFallback !== '' ? $sanitizedFallback : '/';
}
if ($children === '') {
	$children = '<!-- no form fields provided -->';
}

$attributes = array_merge(
	array(
	    'class'  => 'ran-forms__form',
	    'method' => $method,
	    'action' => $action,
	),
	$attributes
);

if ($hasFiles && !array_key_exists('enctype', $attributes)) {
	$attributes['enctype'] = 'multipart/form-data';
}

$formatAttributes = static function (array $attrs): string {
	$parts = array();
	foreach ($attrs as $key => $value) {
		if ($value === null || $value === '' || $value === false) {
			continue;
		}
		$parts[] = sprintf('%s="%s"', esc_attr((string) $key), esc_attr((string) $value));
	}

	return $parts ? ' ' . implode(' ', $parts) : '';
};

$nonceAction = isset($context['nonce_action']) && $context['nonce_action'] !== ''
	? (string) $context['nonce_action']
	: null;
$nonceField = isset($context['nonce_field']) && $context['nonce_field'] !== ''
	? (string) $context['nonce_field']
	: '_wpnonce';

ob_start();
if (!empty($errors)): ?>
    <div class="ran-forms__form-errors" role="alert">
        <p class="screen-reader-text" role="heading" aria-level="2">
            <?php echo esc_html($translator->__('Please fix the errors below')); ?>
        </p>
        <ul>
            <?php foreach ($errors as $message): ?>
                <li><?php echo esc_html((string) $message); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif;

if (!empty($notices)): ?>
    <div class="ran-forms__form-notices" role="status">
        <ul>
            <?php foreach ($notices as $message): ?>
                <li><?php echo esc_html((string) $message); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif;

$nonceFieldMarkup = '';
if ($nonceAction !== null) {
	$nonceFieldMarkup = function_exists('wp_nonce_field') ? wp_nonce_field($nonceAction, $nonceField, true, false) : '';
}

$formAttributes = $formatAttributes($attributes);

ob_start();
?>
<div class="ran-forms__form-wrapper">
	<?php if (!empty($errors)): ?>
		<div class="ran-forms__form-errors" role="alert">
			<p class="screen-reader-text" role="heading" aria-level="2">
				<?php echo esc_html($translator->__('Please fix the errors below')); ?>
			</p>
			<ul>
				<?php foreach ($errors as $message): ?>
					<li><?php echo esc_html((string) $message); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if (!empty($notices)): ?>
		<div class="ran-forms__form-notices" role="status">
			<ul>
				<?php foreach ($notices as $message): ?>
					<li><?php echo esc_html((string) $message); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form<?php echo $formAttributes; ?>>
		<?php echo $nonceFieldMarkup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
		<div class="ran-forms__form-inner">
			<?php // Children should come from reusable field templates or sanitized HTML.?>
			<?php echo $children; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>
		</div>
	</form>
</div>
<?php
$markup = (string) ob_get_clean();

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: $markup,
	script: null,
	style: null,
	requires_media: false,
	repeatable: false,
	context_schema: array(
	    'required' => array('children'),
	    'optional' => array('action', 'method', 'attributes', 'has_files', 'nonce_action', 'nonce_field', 'errors', 'notices'),
	    'defaults' => array(
	        'action'       => '',
	        'method'       => 'POST',
	        'attributes'   => array(),
	        'has_files'    => false,
	        'nonce_action' => null,
	        'nonce_field'  => '_wpnonce',
	        'errors'       => array(),
	        'notices'      => array(),
	    ),
	),
	component_type: 'template'
);
