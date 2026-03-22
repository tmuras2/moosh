<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Console\Adapter;

use Moosh2\Console\OutputInterface;
use Symfony\Component\Console\Output\OutputInterface as SymfonyOutput;

/**
 * Adapts Symfony's OutputInterface to moosh's OutputInterface.
 */
final class SymfonyOutputAdapter implements OutputInterface
{
    public function __construct(
        private readonly SymfonyOutput $inner,
    ) {
    }

    public function write(string $text): void
    {
        $this->inner->write($text);
    }

    public function writeln(string $text): void
    {
        $this->inner->writeln($text);
    }

    public function isVerbose(): bool
    {
        return $this->inner->isVerbose();
    }
}
