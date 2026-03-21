<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Command;

use Moosh2\Attribute\SinceVersion;
use Moosh2\Bootstrap\BootstrapLevel;
use Moosh2\Bootstrap\MoodleBootstrapper;
use Moosh2\Bootstrap\MoodleVersion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract base for all moosh2 commands.
 *
 * Subclasses declare their bootstrap level and implement handle().
 * The base class takes care of bootstrapping Moodle before handle() is called
 * and checks #[SinceVersion] constraints.
 */
abstract class BaseCommand extends Command
{
    /**
     * The bootstrap level this command requires.
     * Override in subclasses to change the default.
     */
    protected BootstrapLevel $bootstrapLevel = BootstrapLevel::FullNoAdminCheck;

    /**
     * Implement the actual command logic here.
     */
    abstract protected function handle(InputInterface $input, OutputInterface $output): int;

    /**
     * Return the active handler for this command, if any.
     *
     * Override in subclasses that delegate to version-specific handlers
     * so the base class can query handler-specific settings (e.g. bootstrap level).
     */
    protected function getActiveHandler(): ?BaseHandler
    {
        return null;
    }

    /**
     * Resolve the effective bootstrap level.
     *
     * Uses the handler's level when the active handler specifies one,
     * otherwise falls back to the command's own bootstrapLevel property.
     */
    protected function getEffectiveBootstrapLevel(): BootstrapLevel
    {
        return $this->getActiveHandler()?->getBootstrapLevel() ?? $this->bootstrapLevel;
    }

    /**
     * Symfony Console entry point — bootstraps Moodle then delegates to handle().
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check class-level #[SinceVersion].
        $bootstrapper = $this->getBootstrapper($input, $output);
        $effectiveLevel = $this->getEffectiveBootstrapLevel();

        if ($bootstrapper !== null) {
            if (!$this->meetsVersionRequirement($bootstrapper->getVersion())) {
                $attr = $this->getSinceVersionAttribute();
                $output->writeln(sprintf(
                    '<error>This command requires Moodle %s or later.</error>',
                    $attr->version,
                ));
                return Command::FAILURE;
            }

            $bootstrapper->bootstrap(
                $effectiveLevel,
                $input->getOption('user'),
                $input->getOption('no-login'),
            );
        }

        return $this->handle($input, $output);
    }

    /**
     * Check the class-level #[SinceVersion] attribute against the running Moodle.
     */
    private function meetsVersionRequirement(MoodleVersion $moodle): bool
    {
        $attr = $this->getSinceVersionAttribute();
        if ($attr === null) {
            return true;
        }

        return $moodle->isAtLeast($attr->version);
    }

    private function getSinceVersionAttribute(): ?SinceVersion
    {
        $ref = new \ReflectionClass($this);
        $attrs = $ref->getAttributes(SinceVersion::class);
        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance();
    }

    /**
     * Resolve the MoodleBootstrapper from the Application.
     * Returns null when no Moodle directory is found and bootstrap is None.
     */
    private function getBootstrapper(InputInterface $input, OutputInterface $output): ?MoodleBootstrapper
    {
        /** @var \Moosh2\Application $app */
        $app = $this->getApplication();

        $bootstrapper = $app->getBootstrapper($input, $output);

        if ($bootstrapper === null && $this->getEffectiveBootstrapLevel() !== BootstrapLevel::None) {
            throw new \RuntimeException(
                'Could not find a Moodle installation. '
                . 'Run moosh from within a Moodle directory or use --moodle-path.',
            );
        }

        return $bootstrapper;
    }
}
