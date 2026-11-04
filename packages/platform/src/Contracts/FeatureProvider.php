<?php

namespace Nreljed\Platform\Contracts;

use Nreljed\Platform\Contracts\Capabilities\HasAdminResources;
use Nreljed\Platform\Contracts\Capabilities\HasContentEditor;
use Nreljed\Platform\Contracts\Capabilities\HasContentType;
use Nreljed\Platform\Contracts\Capabilities\HasDashboardWidgets;
use Nreljed\Platform\Contracts\Capabilities\HasDataBackup;
use Nreljed\Platform\Contracts\Capabilities\HasDeactivation;
use Nreljed\Platform\Contracts\Capabilities\HasHealthChecks;
use Nreljed\Platform\Contracts\Capabilities\HasMedia;
use Nreljed\Platform\Contracts\Capabilities\HasNavigation;
use Nreljed\Platform\Contracts\Capabilities\HasNotifications;
use Nreljed\Platform\Contracts\Capabilities\HasPermissions;
use Nreljed\Platform\Contracts\Capabilities\HasProfileExtension;
use Nreljed\Platform\Contracts\Capabilities\HasSettings;
use Nreljed\Platform\Contracts\Capabilities\HasSharedData;
use Nreljed\Platform\Contracts\Capabilities\HasSitemap;

/**
 * Every capability at once.
 *
 * @deprecated since 0.3.0 — implement {@see Feature} plus only the capability
 *             interfaces you actually support. This composite exists so modules
 *             written against the pre-0.3 core keep working unchanged; it is
 *             removed in v1.0.0.
 *
 * The `InteractsWithFeatures` trait supplies a safe no-op default for every
 * method here except `name()` and `slug()`, which every feature must declare.
 */
interface FeatureProvider extends Feature, HasAdminResources, HasContentEditor, HasContentType, HasDashboardWidgets, HasDataBackup, HasDeactivation, HasHealthChecks, HasMedia, HasNavigation, HasNotifications, HasPermissions, HasProfileExtension, HasSettings, HasSharedData, HasSitemap {}
