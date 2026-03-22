<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Console;

/**
 * Bitmask constants for command option modes.
 */
final class OptionMode
{
    public const VALUE_NONE = 1;
    public const VALUE_REQUIRED = 2;
    public const VALUE_IS_ARRAY = 4;
}
