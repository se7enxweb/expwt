<?php
/**
 * The audit taxonomy branch of the website sidebar: what its calendar moves (E6) and the focal points it sets (E7). Registered in
 * settings/audit.ini.append.php. Everything else the sidebar changes is recorded under the kernel's own names (a
 * publish, an alias, a move ...), as the administration records it.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtAuditBranch implements expAuditTaxonomyBranch
{
    public function events()
    {
        return array(
            // a draft's publish date, a page's take-down date or a layout variant's time, moved in the calendar
            'content.expwt.schedule.change' => array( 'label' => 'Schedule moved in the website sidebar', 'severity' => 'info', 'channel' => 'content', 'default' => 'on' ),
            // an image's focal point or crop rectangle set in a draft (E7)
            'content.expwt.image.focal' => array( 'label' => 'Image focal point set in the website sidebar', 'severity' => 'info', 'channel' => 'content', 'default' => 'on' ),
        );
    }
}
