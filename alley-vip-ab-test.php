<?php
/**
 * Establishes functionality for A/B testing with WordPress.com VIP's cache varying system.
 *
 * @link https://docs.wpvip.com/technical-references/caching/the-vip-cache-personalization-api/
 * @link https://github.com/Automattic/vip-go-mu-plugins/tree/develop/cache
 *
 * @package Alley_VIP_AB_Test
 */

namespace Alley_VIP_AB_Test;

require_once WPMU_PLUGIN_DIR . '/cache/class-vary-cache.php';
require_once __DIR__ . '/includes/class-test.php';

