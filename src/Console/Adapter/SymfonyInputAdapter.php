<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Console\Adapter;

use Moosh2\Console\InputInterface;
use Symfony\Component\Console\Input\InputInterface as SymfonyInput;

/**
 * Adapts Symfony's InputInterface to moosh's InputInterface.
 */
final class SymfonyInputAdapter implements InputInterface
{
    public function __construct(
        private readonly SymfonyInput $inner,
    ) {
    }

    public function getOption(string $name): mixed
    {
        return $this->inner->getOption($name);
    }

    public function getArgument(string $name): mixed
    {
        return $this->inner->getArgument($name);
    }
}
