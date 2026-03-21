<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract base class for all version-specific command handlers.
 *
 * Subclasses override configureCommand() to register their arguments
 * and options, and implement handle() with the actual command logic.
 */
abstract class BaseHandler
{
    /**
     * Register handler-specific arguments and options on the command.
     *
     * Called during Symfony's configure() phase. Override in subclasses
     * to add arguments and options.
     */
    public function configureCommand(Command $command): void
    {
        // No-op by default.
    }

    /**
     * Execute the handler logic.
     */
    abstract public function handle(InputInterface $input, OutputInterface $output): int;
}
