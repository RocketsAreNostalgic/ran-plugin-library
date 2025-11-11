<?php
declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component;

/**
 * Canonical component classifications used by ComponentRenderResult.
 */
enum ComponentType: string {
	case FormField     = 'input';
	case LayoutWrapper = 'layout_wrapper';
	case Display       = 'display';
	case Template      = 'template';
}
