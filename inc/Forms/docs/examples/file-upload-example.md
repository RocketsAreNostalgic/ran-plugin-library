# File Upload Form Example

This example demonstrates how to combine `form` and `fields.file` templates with `FileUploadHelper::handle()` for a secure upload flow.

## Template rendering

```php
use Ran\PluginLib\Forms\FieldViewFactory;
use Ran\PluginLib\Forms\Component\ComponentLoader;

$loader = new ComponentLoader(__DIR__ . '/../views');
$fields = new FieldViewFactory($loader);

$formBody = $fields->render('file', array(
    'name'        => 'attachment',
    'description' => 'Accepted types: JPG, PNG (max 2MB).',
    'accept'      => array('image/jpeg', 'image/png'),
    'required'    => true,
    'existing_files' => isset($currentFiles) ? $currentFiles : array(),
));

$formHtml = $loader->render('form', array(
    'action'        => esc_url(home_url('/contact/submit')),
    'nonce_action'  => 'contact_form',
    'has_files'     => true,
    'children'      => $formBody,
    'errors'        => $errors,
    'notices'       => $notices,
));

echo $formHtml;
```

## Handler example

```php
use Ran\PluginLib\Forms\Upload\FileUploadHelper;

$errors  = array();
$notices = array();

$result = FileUploadHelper::handle(array(
    'file_key'     => 'attachment',
    'nonce_action' => 'contact_form',
));

if ($result['success']) {
    $uploadedFile = $result['file'];
    // Persist metadata or attach to post.
    $notices[] = sprintf('File uploaded: %s', esc_html($uploadedFile['url']));
} else {
    // Map identifiers to user-friendly copy.
    $messages = array(
        'invalid_nonce'              => 'Your session expired. Please try again.',
        'insufficient_permissions'   => 'You do not have permission to upload files.',
        'file_too_large'             => 'The file exceeds the allowed size.',
        'blocked_by_extension'       => 'This file type is not allowed.',
        'file_missing'               => 'Please choose a file to upload.',
        'upload_failed'              => 'Upload failed. Please try again.',
    );
    $errors[] = $messages[$result['error']] ?? 'Upload failed. Please try again.';
}
```

## Minimal CSS

```css
.ran-forms__form-errors {
  border: 2px solid #d63638;
  padding: 1rem;
  background: #fdf2f2;
  color: #690c0c;
  margin-bottom: 1.5rem;
}

.ran-forms__form-notices {
  border: 1px solid #14532d;
  padding: 1rem;
  background: #ecfdf5;
  color: #064e3b;
  margin-bottom: 1.5rem;
}

.screen-reader-text {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.ran-forms__file-existing {
  margin: 0.75rem 0 0;
  padding-left: 1.125rem;
}
```

## Notes

- Ensure the form tag includes `enctype="multipart/form-data"` when rendering file uploads.
- `FileUploadHelper::handle()` does not support multiple files in one submission; loop over `$_FILES` entries if you need multi-upload.
- Always sanitize and store metadata server-side before presenting URLs back to the client.
