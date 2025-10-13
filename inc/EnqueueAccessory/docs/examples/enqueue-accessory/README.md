# EnqueueAccessory Usage Examples

Examples demonstrating `EnqueuePublic` and `EnqueueAdmin` with scripts, script modules, and media tools.

- public-scripts-basic.php: `EnqueuePublic` + standard scripts
- public-script-modules.php: `EnqueuePublic` + script modules
- admin-scripts-basic.php: `EnqueueAdmin` + standard scripts
- admin-script-modules.php: `EnqueueAdmin` + script modules
- admin-media-tools.php: `EnqueueAdmin` + WordPress media tools (`wp_enqueue_media()`)
- admin-media-picker.php: `EnqueueAdmin` + Forms `MediaPickerAssets` (dependency-injected)

Timing pattern follows `docs/TFS-003-Library-Initialization-Timing.md`:

- Initialize on `plugins_loaded`
- Add assets on `init`
- Staging occurs automatically via `load()` (wp_enqueue_scripts/admin_enqueue_scripts)
