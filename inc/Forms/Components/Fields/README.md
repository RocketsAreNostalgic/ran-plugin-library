# Field View Templates

Reusable form field partials rendered through `Ran\PluginLib\Forms\ComponentLoader`.

- `fields.fields.input`
- `fields.textarea`
- `fields.checkbox`
- `fields.checkbox-option`
- `fields.checkbox-group`
- `fields.radio-group`
- `fields.radio-option`
- `fields.select`
- `fields.multi-select`
- `fields.file`

Each template expects a `$context` array and returns a rendered HTML string. View alias names mirror the folder structure (`fields/*.php`).

## Usage

```php
$logger = new \Ran\PluginLib\Util\Logger();
$loader = new ComponentLoader(__DIR__ . '/views', $logger);
$html   = $loader->render('fields.fields.input', array(
    'attributes'   => array(
        'name' => 'example',
        'value' => '42',
    ),
    'description' => 'Example field',
));
```

Uploading files requires server-side handling with WordPress helpers:

```php
$fileField = $loader->render('fields.file', array(
    'name'        => 'attachment',
    'description' => 'Accepted types: JPG, PNG (max 2MB).',
    'accept'      => array('image/jpeg', 'image/png'),
));

// In the submission handler:
check_admin_referer('contact_form');
$uploaded = wp_handle_upload($_FILES['attachment'], array('test_form' => false));
```

A basic select field can be rendered with:

```php
$select = $loader->render('fields.select', array(
    'name'    => 'color',
    'value'   => 'blue',
    'options' => array(
        array('value' => 'red', 'label' => 'Red'),
        array('value' => 'blue', 'label' => 'Blue'),
    ),
));
```

For multi-select with grouped options:

```php
$multi = $loader->render('fields.multi-select', array(
    'name'   => 'fruits[]',
    'values' => array('apple', 'banana'),
    'options' => array(
        array('value' => 'apple', 'label' => 'Apple', 'group' => 'Common'),
        array('value' => 'banana', 'label' => 'Banana', 'group' => 'Common'),
        array('value' => 'dragonfruit', 'label' => 'Dragonfruit', 'group' => 'Special'),
    ),
));
```

See individual template docblocks for accepted context keys.
