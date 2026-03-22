<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Console\Adapter;

use Moosh2\Console\ArgumentMode;
use Moosh2\Console\CommandDefinition;
use Moosh2\Console\OptionMode;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Adapts moosh's CommandDefinition to Symfony's Command argument/option registration.
 */
final class SymfonyCommandDefinition implements CommandDefinition
{
    public function __construct(
        private readonly Command $command,
    ) {
    }

    public function addArgument(string $name, int $mode = 0, string $description = ''): static
    {
        $symfonyMode = 0;
        if ($mode & ArgumentMode::OPTIONAL) {
            $symfonyMode |= InputArgument::OPTIONAL;
        }
        if ($mode & ArgumentMode::IS_ARRAY) {
            $symfonyMode |= InputArgument::IS_ARRAY;
        }

        $this->command->addArgument($name, $symfonyMode ?: InputArgument::OPTIONAL, $description);

        return $this;
    }

    public function addOption(string $name, ?string $shortcut = null, int $mode = 0, string $description = ''): static
    {
        $symfonyMode = 0;
        if ($mode & OptionMode::VALUE_NONE) {
            $symfonyMode |= InputOption::VALUE_NONE;
        }
        if ($mode & OptionMode::VALUE_REQUIRED) {
            $symfonyMode |= InputOption::VALUE_REQUIRED;
        }
        if ($mode & OptionMode::VALUE_IS_ARRAY) {
            $symfonyMode |= InputOption::VALUE_IS_ARRAY;
        }

        $this->command->addOption($name, $shortcut, $symfonyMode ?: InputOption::VALUE_NONE, $description);

        return $this;
    }
}
