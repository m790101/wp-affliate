<?php
/**
 * Integrations: Caldera Forms Extras
 *
 * @package     AffiliateWP
 * @subpackage  Integrations/Extras
 * @copyright   Copyright (c) 2016, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
 */

$integration = new Affiliate_WP_Caldera_Forms;

/**
 * This file is the processor panel in Caldera Forms settings.
 */
echo Caldera_Forms_Processor_UI::config_fields( $integration->fields() );
