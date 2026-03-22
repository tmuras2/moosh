<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Console\Adapter;

use Moosh2\Command\BaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Wraps a moosh BaseCommand as a Symfony Console Command.
 *
 * This is the only place where moosh commands touch Symfony directly.
 */
final class SymfonyCommandAdapter extends Command
{
    public function __construct(
        private readonly BaseCommand $mooshCommand,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName($this->mooshCommand->getName());

        $aliases = $this->mooshCommand->getAliases();
        if ($aliases) {
            $this->setAliases($aliases);
        }

        $this->setDescription($this->mooshCommand->getDescription());

        $help = $this->mooshCommand->getHelp();
        if ($help !== '') {
            $this->setHelp($help);
        }

        $this->mooshCommand->configure(new SymfonyCommandDefinition($this));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->mooshCommand->execute(
            new SymfonyInputAdapter($input),
            new SymfonyOutputAdapter($output),
        );
    }

    public function getMooshCommand(): BaseCommand
    {
        return $this->mooshCommand;
    }
}
