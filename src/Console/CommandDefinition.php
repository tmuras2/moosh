<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Console;

/**
 * Abstraction for registering command arguments and options.
 *
 * Decouples moosh commands from any specific console framework.
 */
interface CommandDefinition
{
    public function addArgument(string $name, int $mode = 0, string $description = ''): static;

    public function addOption(string $name, ?string $shortcut = null, int $mode = 0, string $description = ''): static;
}
