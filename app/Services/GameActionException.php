<?php

namespace App\Services;

/**
 * Thrown by any game action service (Work, Training, Market, ...) when a
 * server-side validation/affordability check fails. The message is
 * user-facing — components catch this and flash it directly.
 */
class GameActionException extends \RuntimeException {}
