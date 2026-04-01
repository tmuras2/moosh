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
use Moosh2\Command\Activity\ActivityAddCommand;
use Moosh2\Command\Activity\ActivityInfoCommand;
use Moosh2\Command\Admin\AdminLoginCommand;
use Moosh2\Command\Audit\AuditBruteforceCommand;
use Moosh2\Command\Audit\AuditPasswordCommand;
use Moosh2\Command\Auth\AuthInfoCommand;
use Moosh2\Command\Auth\AuthListCommand;
use Moosh2\Command\Auth\AuthModCommand;
use Moosh2\Command\Badge\BadgeAddCommand;
use Moosh2\Command\Badge\BadgeDeleteCommand;
use Moosh2\Command\Badge\BadgeInfoCommand;
use Moosh2\Command\Badge\BadgeModCommand;
use Moosh2\Command\Activity\ActivityDeleteCommand;
use Moosh2\Command\Activity\ActivityModCommand;
use Moosh2\Command\Context\ContextFreezeCommand;
use Moosh2\Command\Context\ContextRebuildCommand;
use Moosh2\Command\Context\ContextUnfreezeCommand;
use Moosh2\Command\Data\DataCheckCommand;
use Moosh2\Command\Event\EventDiscoverCommand;
use Moosh2\Command\Log\LogExportCommand;
use Moosh2\Command\Log\LogUnpackCommand;
use Moosh2\Command\Backup\BackupEmptyFilesCommand;
use Moosh2\Command\Backup\BackupInfoCommand;
use Moosh2\Command\Gradebook\GradebookExportCommand;
use Moosh2\Command\Gradebook\GradebookImportCommand;
use Moosh2\Command\Fontawesome\FontawesomeListCommand;
use Moosh2\Command\Fontawesome\FontawesomeMaplistCommand;
use Moosh2\Command\Fontawesome\FontawesomeRefreshCacheCommand;
use Moosh2\Command\Category\CategoryCreateCommand;
use Moosh2\Command\Category\CategoryDeleteCommand;
use Moosh2\Command\Category\CategoryInfoCommand;
use Moosh2\Command\Category\CategoryListCommand;
use Moosh2\Command\Config\ConfigGetCommand;
use Moosh2\Command\Config\ConfigSetCommand;
use Moosh2\Command\Context\ContextInfoCommand;
use Moosh2\Command\Report\ReportConcurrencyCommand;
use Moosh2\Command\Role\RoleCreateCommand;
use Moosh2\Command\Role\RoleDeleteCommand;
use Moosh2\Command\Role\RoleExportCommand;
use Moosh2\Command\Role\RoleImportCommand;
use Moosh2\Command\Role\RoleListCommand;
use Moosh2\Command\Role\RoleModCommand;
use Moosh2\Command\Role\RoleResetCommand;
use Moosh2\Command\Course\CourseBackupCommand;
use Moosh2\Command\Course\CourseCopyCommand;
use Moosh2\Command\Course\CourseCreateCommand;
use Moosh2\Command\Course\CourseDeleteCommand;
use Moosh2\Command\Course\CourseEnrolCommand;
use Moosh2\Command\Course\CourseResetCommand;
use Moosh2\Command\Course\CourseRestoreCommand;
use Moosh2\Command\Course\CourseUnenrolCommand;
use Moosh2\Command\Course\CourseFindBigImagesCommand;
use Moosh2\Command\Course\CourseInfoCommand;
use Moosh2\Command\Course\CourseLastVisitedCommand;
use Moosh2\Command\Course\CourseListCommand;
use Moosh2\Command\Course\CourseTopCommand;
use Moosh2\Command\Plugin\PluginDownloadCommand;
use Moosh2\Command\Plugin\PluginInstallCommand;
use Moosh2\Command\Plugin\PluginListCommand;
use Moosh2\Command\Plugin\PluginUninstallCommand;
use Moosh2\Command\Plugin\PluginUsageCommand;
use Moosh2\Command\ProfileField\ProfileFieldAddCommand;
use Moosh2\Command\ProfileField\ProfileFieldDeleteCommand;
use Moosh2\Command\ProfileField\ProfileFieldExportCommand;
use Moosh2\Command\ProfileField\ProfileFieldImportCommand;
use Moosh2\Command\ProfileField\ProfileFieldInfoCommand;
use Moosh2\Command\Search\SearchQuestionIdCommand;
use Moosh2\Command\Search\SearchTimestampCommand;
use Moosh2\Command\Sql\SqlCliCommand;
use Moosh2\Command\Sql\SqlDumpCommand;
use Moosh2\Command\Sql\SqlRunCommand;
use Moosh2\Command\Sql\SqlSelectCommand;
use Moosh2\Command\Theme\ThemeInfoCommand;
use Moosh2\Command\Theme\ThemeSettingsExportCommand;
use Moosh2\Command\Theme\ThemeSettingsImportCommand;
use Moosh2\Command\User\UserCreateCommand;
use Moosh2\Command\User\UserDeleteCommand;
use Moosh2\Command\User\UserImportPicturesCommand;
use Moosh2\Command\User\UserInfoCommand;
use Moosh2\Command\User\UserListCommand;
use Moosh2\Command\User\UserModCommand;
use Moosh2\Output\VerboseLogger;
use Moosh2\Service\MockupClock;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class Application extends SymfonyApplication {
    public const VERSION = '2.0';

    private ?string $moodlePath = null;
    private ?MoodleVersion $moodleVersion = null;
    private ?MoodleBootstrapper $bootstrapper = null;
    private bool $bootstrapperResolved = false;

    public function __construct() {
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
    public function getMoodleVersion(): ?MoodleVersion {
        return $this->moodleVersion;
    }

    protected function getDefaultInputDefinition(): \Symfony\Component\Console\Input\InputDefinition {
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
                new InputOption(
                        'run',
                        null,
                        InputOption::VALUE_NONE,
                        'Run command in write-mode. It may modify the database.',
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
    public function getBootstrapper(InputInterface $input, OutputInterface $output): ?MoodleBootstrapper {
        if ($this->bootstrapperResolved) {
            return $this->bootstrapper;
        }

        $this->bootstrapperResolved = true;

        $verbose = new VerboseLogger($output);

        if ($this->moodlePath === null || $this->moodleVersion === null) {
            $verbose->warn('No Moodle installation detected — commands requiring bootstrap will fail');
            return null;
        }

        $verbose->section('Moodle Environment');
        $verbose->detail('Moodle directory', $this->moodlePath);
        $verbose->detail('Branch', $this->moodleVersion->getBranch());
        $verbose->detail('Release', $this->moodleVersion->getRelease());
        $verbose->detail('Numeric version', (string) $this->moodleVersion->getNumericVersion());
        $verbose->done('Moodle installation detected');

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
    private function resolveVersionEarly(): void {
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
    private function extractMoodlePathFromArgv(): ?string {
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

    private function registerCommands(): void {
        $mockupDateTime = getenv('MOCKUP_DATE_TIME');
        $clock = $mockupDateTime !== false ? new MockupClock($mockupDateTime) : null;

        $this->addCommand(new ActivityAddCommand($this->moodleVersion));
        $this->addCommand(new ActivityInfoCommand($this->moodleVersion));
        $this->addCommand(new ActivityDeleteCommand($this->moodleVersion));
        $this->addCommand(new ActivityModCommand($this->moodleVersion));
        $this->addCommand(new AdminLoginCommand($this->moodleVersion));
        $this->addCommand(new AuditBruteforceCommand($this->moodleVersion));
        $this->addCommand(new AuditPasswordCommand($this->moodleVersion));
        $this->addCommand(new AuthListCommand($this->moodleVersion));
        $this->addCommand(new AuthInfoCommand($this->moodleVersion));
        $this->addCommand(new AuthModCommand($this->moodleVersion));
        $this->addCommand(new BadgeAddCommand($this->moodleVersion));
        $this->addCommand(new BadgeInfoCommand($this->moodleVersion));
        $this->addCommand(new BadgeModCommand($this->moodleVersion));
        $this->addCommand(new BadgeDeleteCommand($this->moodleVersion));
        $this->addCommand(new BackupEmptyFilesCommand($this->moodleVersion));
        $this->addCommand(new GradebookExportCommand($this->moodleVersion));
        $this->addCommand(new GradebookImportCommand($this->moodleVersion));
        $this->addCommand(new FontawesomeListCommand($this->moodleVersion));
        $this->addCommand(new FontawesomeMaplistCommand($this->moodleVersion));
        $this->addCommand(new FontawesomeRefreshCacheCommand($this->moodleVersion));
        $this->addCommand(new BackupInfoCommand($this->moodleVersion));
        $this->addCommand(new CategoryCreateCommand($this->moodleVersion));
        $this->addCommand(new CategoryDeleteCommand($this->moodleVersion));
        $this->addCommand(new CategoryListCommand($this->moodleVersion));
        $this->addCommand(new CategoryInfoCommand($this->moodleVersion));
        $this->addCommand(new ConfigGetCommand($this->moodleVersion));
        $this->addCommand(new ConfigSetCommand($this->moodleVersion));
        $this->addCommand(new ContextInfoCommand($this->moodleVersion));
        $this->addCommand(new ContextFreezeCommand($this->moodleVersion));
        $this->addCommand(new ContextUnfreezeCommand($this->moodleVersion));
        $this->addCommand(new ContextRebuildCommand($this->moodleVersion));
        $this->addCommand(new DataCheckCommand($this->moodleVersion));
        $this->addCommand(new EventDiscoverCommand($this->moodleVersion));
        $this->addCommand(new LogExportCommand($this->moodleVersion));
        $this->addCommand(new LogUnpackCommand($this->moodleVersion));
        $this->addCommand(new CourseBackupCommand($this->moodleVersion));
        $this->addCommand(new CourseCopyCommand($this->moodleVersion));
        $this->addCommand(new CourseCreateCommand($this->moodleVersion));
        $this->addCommand(new CourseDeleteCommand($this->moodleVersion));
        $this->addCommand(new CourseEnrolCommand($this->moodleVersion));
        $this->addCommand(new CourseResetCommand($this->moodleVersion));
        $this->addCommand(new CourseRestoreCommand($this->moodleVersion));
        $this->addCommand(new CourseUnenrolCommand($this->moodleVersion));
        $this->addCommand(new CourseFindBigImagesCommand($this->moodleVersion));
        $this->addCommand(new CourseLastVisitedCommand($this->moodleVersion));
        $this->addCommand(new CourseListCommand($this->moodleVersion, $clock));
        $this->addCommand(new CourseInfoCommand($this->moodleVersion));
        $this->addCommand(new CourseTopCommand($this->moodleVersion));
        $this->addCommand(new PluginDownloadCommand($this->moodleVersion));
        $this->addCommand(new PluginInstallCommand($this->moodleVersion));
        $this->addCommand(new PluginListCommand($this->moodleVersion));
        $this->addCommand(new PluginUninstallCommand($this->moodleVersion));
        $this->addCommand(new PluginUsageCommand($this->moodleVersion));
        $this->addCommand(new ReportConcurrencyCommand($this->moodleVersion));
        $this->addCommand(new RoleCreateCommand($this->moodleVersion));
        $this->addCommand(new RoleDeleteCommand($this->moodleVersion));
        $this->addCommand(new RoleExportCommand($this->moodleVersion));
        $this->addCommand(new RoleImportCommand($this->moodleVersion));
        $this->addCommand(new RoleListCommand($this->moodleVersion));
        $this->addCommand(new RoleModCommand($this->moodleVersion));
        $this->addCommand(new RoleResetCommand($this->moodleVersion));
        $this->addCommand(new ProfileFieldAddCommand($this->moodleVersion));
        $this->addCommand(new ProfileFieldDeleteCommand($this->moodleVersion));
        $this->addCommand(new ProfileFieldExportCommand($this->moodleVersion));
        $this->addCommand(new ProfileFieldImportCommand($this->moodleVersion));
        $this->addCommand(new ProfileFieldInfoCommand($this->moodleVersion));
        $this->addCommand(new SearchQuestionIdCommand($this->moodleVersion));
        $this->addCommand(new SearchTimestampCommand($this->moodleVersion));
        $this->addCommand(new SqlCliCommand($this->moodleVersion));
        $this->addCommand(new SqlDumpCommand($this->moodleVersion));
        $this->addCommand(new SqlRunCommand($this->moodleVersion));
        $this->addCommand(new SqlSelectCommand($this->moodleVersion));
        $this->addCommand(new ThemeInfoCommand($this->moodleVersion));
        $this->addCommand(new ThemeSettingsExportCommand($this->moodleVersion));
        $this->addCommand(new ThemeSettingsImportCommand($this->moodleVersion));
        $this->addCommand(new UserCreateCommand($this->moodleVersion));
        $this->addCommand(new UserDeleteCommand($this->moodleVersion));
        $this->addCommand(new UserListCommand($this->moodleVersion, $clock));
        $this->addCommand(new UserImportPicturesCommand($this->moodleVersion));
        $this->addCommand(new UserInfoCommand($this->moodleVersion));
        $this->addCommand(new UserModCommand($this->moodleVersion));
    }
}
