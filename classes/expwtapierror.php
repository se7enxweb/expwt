<?php
/**
 * A refusal of a sidebar action: its HTTP status, a code the panels act on (forbidden, not_found, conflict, locked,
 * bad_input ...) and a message for the user (in English; the panels show the translation of their own strings).
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtApiError extends Exception
{
    /** @var int */
    public $status;

    /** @var string */
    public $errorCode;

    /** @var array */
    public $details;

    public function __construct( $status, $errorCode, $message, array $details = array() )
    {
        parent::__construct( (string)$message );
        $this->status = (int)$status;
        $this->errorCode = (string)$errorCode;
        $this->details = $details;
    }
}
