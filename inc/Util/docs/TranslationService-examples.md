# TranslationService Usage Examples

The `TranslationService` provides flexible translation capabilities with both domain-level and message-level overrides.

## Basic Usage

```php
use Ran\PluginLib\Util\TranslationService;
use Ran\PluginLib\Forms\Component\Validate\ValidatorBase;

// Create a service for validators
$translator = ValidatorBase::create_translation_service();

// Or create with custom text domain
$translator = ValidatorBase::create_translation_service('my-plugin');

// Translate a message
$message = $translator->translate('This field is required.');
```

## Consumer Customization

### Override Specific Messages

```php
// Override individual validation messages
add_filter('ran/plugin_lib/forms/validator/translation_overrides', function($overrides) {
    return array_merge($overrides, [
        'This field is required.' => 'You must fill this out!',
        'Please enter a valid email address.' => 'That email looks wrong.',
        'Please select a valid option.' => 'Pick something from the list.',
    ]);
});
```

### Change Translation Domain

```php
// Use consumer's text domain for all validator messages
add_filter('ran/plugin_lib/forms/validator/translation_domain', function($domain) {
    return 'my-plugin-textdomain';
});

// Or use Config to get the consumer's domain
add_filter('ran/plugin_lib/forms/validator/translation_domain', function($domain) use ($config) {
    return $config->get_config()['TextDomain'];
});
```

### Component-Specific Overrides

```php
// Only override validator messages
add_filter('ran/plugin_lib/forms/validator/translation_domain', function($domain) {
    return 'my-plugin';
});

// Different domain for normalizer messages
add_filter('ran/plugin_lib/forms/normalizer/translation_domain', function($domain) {
    return 'my-plugin-normalizer';
});

// Keep builder messages with library domain (no filter)
```

### Mixed Approach

```php
// Change domain for most messages
add_filter('ran/plugin_lib/forms/validator/translation_domain', function($domain) {
    return 'my-plugin';
});

// But override specific messages with custom text
add_filter('ran/plugin_lib/forms/validator/translation_overrides', function($overrides) {
    return array_merge($overrides, [
        'This field is required.' => 'Hey! You forgot to fill this in.',
    ]);
});
```

## Available Services

Form components now provide their own factory methods:

- `ValidatorBase::create_translation_service()` - Hook prefix: `ran/plugin_lib/forms/validator`
- `NormalizerBase::create_translation_service()` - Hook prefix: `ran/plugin_lib/forms/normalizer`
- `BuilderBase::create_translation_service()` - Hook prefix: `ran/plugin_lib/forms/builder`

For other components, use the generic domain creator:

- `TranslationService::for_domain('settings')` - Hook prefix: `ran/plugin_lib/settings`
- `TranslationService::for_domain('options')` - Hook prefix: `ran/plugin_lib/options`

## Generic Domain Creation

```php
// Create services for any domain
$apiTranslator = TranslationService::for_domain('api/client');
$cacheTranslator = TranslationService::for_domain('cache/redis');
$authTranslator = TranslationService::for_domain('auth/oauth', 'my-plugin-textdomain');

// Hook prefixes will be:
// ran/plugin_lib/api/client
// ran/plugin_lib/cache/redis
// ran/plugin_lib/auth/oauth
```

## Template Helpers

For form view templates that can't use class-based translation methods:

```php
// Direct TranslationService usage in templates
<?php
$wrappers = \Ran\PluginLib\Util\WPWrappers::instance();
$translator = \Ran\PluginLib\Util\TranslationService::for_domain('forms/view');
echo $wrappers->_do_esc_html__service('Please fix the errors below', $translator);
?>

// Direct domain specification
<?php echo ran_esc_html_translate_domain('This field is required', 'forms/validator', 'my-plugin'); ?>

// WPWrappers with TranslationServiceTrait
<?php
$wrappers = \Ran\PluginLib\Util\WPWrappers::instance();
echo $wrappers->_do_esc_html_translate_domain('This field is required', 'forms/validator', 'my-plugin');
?>

// Consumers can override template translations
add_filter('ran/plugin_lib/forms/view/translation_domain', function($domain) {
    return 'my-plugin';
});
```

## Custom Services

```php
// Create a completely custom service
$customTranslator = new TranslationService('my-plugin', 'my_plugin/custom_component');

// Or use the domain helper with custom text domain
$customTranslator = TranslationService::for_domain('custom/component', 'my-plugin');

// Consumers can then override with:
add_filter('ran/plugin_lib/custom/component/translation_domain', function($domain) {
    return 'consumer-domain';
});
```
