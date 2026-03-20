<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2;

use Moosh2\Bootstrap\MoodleBootstrapper;
use Moosh2\Bootstrap\MoodlePathResolver;
use Moosh2\Bootstrap\MoodleVersion;
use Moosh2\Command\Course\CourseListCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class Application extends SymfonyApplication
{
    public const VERSION = '2.0.0-dev';

    private ?MoodleBootstrapper $bootstrapper = null;
    private bool $bootstrapperResolved = false;

    public function __construct()
    {
        parent::__construct('moosh', self::VERSION);

        $this->registerCommands();
    }

    protected function getDefaultInputDefinition(): \Symfony\Component\Console\Input\InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOptions([
            new InputOption(
                'moodle-path',
                'p',
                InputOption::VALUE_REQUIRED,
                'Path to the Moodle directory',
            ),
            new InputOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED,
                'Moodle user to log in as (default: admin)',
            ),
            new InputOption(
                'no-login',
                'l',
                InputOption::VALUE_NONE,
                'Do not log in as any user',
            ),
            new InputOption(
                'no-user-check',
                null,
                InputOption::VALUE_NONE,
                'Do not check if Moodle data is owned by the current user',
            ),
            new InputOption(
                'performance',
                't',
                InputOption::VALUE_NONE,
                'Show performance information including timings',
            ),
            new InputOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output format: table, csv, json',
                'table',
            ),
        ]);

        return $definition;
    }

    /**
     * Resolve (and cache) the MoodleBootstrapper for the current invocation.
     *
     * Returns null if no Moodle directory can be found (commands with
     * BootstrapLevel::None can still run).
     */
    public function getBootstrapper(InputInterface $input, OutputInterface $output): ?MoodleBootstrapper
    {
        if ($this->bootstrapperResolved) {
            return $this->bootstrapper;
        }

        $this->bootstrapperResolved = true;

        $moodlePath = $input->getOption('moodle-path');
        if ($moodlePath === null) {
            $resolver = new MoodlePathResolver();
            $moodlePath = $resolver->resolve();
        }

        if ($moodlePath === null) {
            return null;
        }

        $version = MoodleVersion::fromMoodleDir($moodlePath);

        if ($output->isVerbose()) {
            $output->writeln(sprintf(
                'Moodle directory: %s (branch %s)',
                $moodlePath,
                $version->getBranch(),
            ));
        }

        $this->bootstrapper = new MoodleBootstrapper($moodlePath, $version, $output);

        return $this->bootstrapper;
    }

    private function registerCommands(): void
    {
        $this->add(new CourseListCommand());
    }
}
