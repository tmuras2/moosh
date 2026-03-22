<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Console;

/**
 * Abstraction for command input (options and arguments).
 *
 * Decouples moosh commands from any specific console framework.
 */
interface InputInterface
{
    public function getOption(string $name): mixed;

    public function getArgument(string $name): mixed;
}
