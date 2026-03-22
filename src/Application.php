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
use Moosh2\Console\Adapter\SymfonyCommandAdapter;
use Moosh2\Console\InputInterface;
use Moosh2\Console\OutputInterface;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputOption;

final class Application extends SymfonyApplication
{
    public const VERSION = '2.0.0-dev';

    private ?string $moodlePath = null;
    private ?MoodleVersion $moodleVersion = null;
    private ?MoodleBootstrapper $bootstrapper = null;
    private bool $bootstrapperResolved = false;

    public function __construct()
    {
        parent::__construct('moosh', self::VERSION);

        $this->resolveVersionEarly();
        $this->registerCommands();
    }

    /**
     * Return the Moodle version detected at startup.
     *
     * Available before Symfony input parsing — safe to call during configure().
     * Returns null if no Moodle installation was found.
     */
    public function getMoodleVersion(): ?MoodleVersion
    {
        return $this->moodleVersion;
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
     * Uses the Moodle path/version already detected in the constructor.
     * Returns null if no Moodle directory was found (commands with
     * BootstrapLevel::None can still run).
     */
    public function getBootstrapper(InputInterface $input, OutputInterface $output): ?MoodleBootstrapper
    {
        if ($this->bootstrapperResolved) {
            return $this->bootstrapper;
        }

        $this->bootstrapperResolved = true;

        if ($this->moodlePath === null || $this->moodleVersion === null) {
            return null;
        }

        if ($output->isVerbose()) {
            $output->writeln(sprintf(
                'Moodle directory: %s (branch %s)',
                $this->moodlePath,
                $this->moodleVersion->getBranch(),
            ));
        }

        $this->bootstrapper = new MoodleBootstrapper($this->moodlePath, $this->moodleVersion, $output);

        return $this->bootstrapper;
    }

    /**
     * Detect the Moodle installation path and version early, before commands
     * are registered. This allows commands to select version-specific handlers
     * during Symfony's configure() phase.
     *
     * Scans $_SERVER['argv'] for --moodle-path / -p since Symfony input
     * parsing has not happened yet at this point.
     */
    private function resolveVersionEarly(): void
    {
        $moodlePath = $this->extractMoodlePathFromArgv();

        if ($moodlePath === null) {
            $resolver = new MoodlePathResolver();
            $moodlePath = $resolver->resolve();
        }

        if ($moodlePath === null) {
            return;
        }

        $versionFile = $moodlePath . '/version.php';
        if (!file_exists($versionFile)) {
            return;
        }

        $this->moodlePath = $moodlePath;
        $this->moodleVersion = MoodleVersion::fromMoodleDir($moodlePath);
    }

    /**
     * Extract --moodle-path / -p value from raw argv before Symfony parses input.
     */
    private function extractMoodlePathFromArgv(): ?string
    {
        $argv = $_SERVER['argv'] ?? [];

        for ($i = 0, $count = count($argv); $i < $count; $i++) {
            $arg = $argv[$i];

            // --moodle-path=/some/path
            if (str_starts_with($arg, '--moodle-path=')) {
                return substr($arg, strlen('--moodle-path='));
            }

            // --moodle-path /some/path or -p /some/path
            if (($arg === '--moodle-path' || $arg === '-p') && isset($argv[$i + 1])) {
                return $argv[$i + 1];
            }
        }

        return null;
    }

    private function registerCommands(): void
    {
        $command = new CourseListCommand($this->moodleVersion);
        $command->setApplication($this);
        $this->add(new SymfonyCommandAdapter($command));
    }
}
