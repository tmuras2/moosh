<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Console;

/**
 * Abstraction for command output.
 *
 * Decouples moosh commands from any specific console framework.
 */
interface OutputInterface
{
    public function write(string $text): void;

    public function writeln(string $text): void;

    public function isVerbose(): bool;
}
