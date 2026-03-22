<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Command\Course;

use Moosh2\Bootstrap\BootstrapLevel;
use Moosh2\Bootstrap\MoodleVersion;
use Moosh2\Command\BaseCommand;
use Moosh2\Command\BaseHandler;
use Moosh2\Console\CommandDefinition;
use Moosh2\Console\InputInterface;
use Moosh2\Console\OutputInterface;
use Moosh2\Service\ClockInterface;
use Moosh2\Service\SystemClock;

/**
 * List courses matching optional search criteria.
 *
 * Replaces Moosh\Command\Moodle39\Course\CourseList.
 * Canonical name: course:list  |  Alias: course-list
 *
 * Delegates argument/option definition and execution to a
 * version-specific handler selected at construction time.
 */
class CourseListCommand extends BaseCommand
{
    protected BootstrapLevel $bootstrapLevel = BootstrapLevel::Full;

    private BaseHandler $handler;

    public function __construct(?MoodleVersion $moodleVersion, ?ClockInterface $clock = null)
    {
        $this->handler = $this->resolveHandler($moodleVersion, $clock ?? new SystemClock());
    }

    public function getName(): string
    {
        return 'course:list';
    }

    public function getAliases(): array
    {
        return ['course-list'];
    }

    public function getDescription(): string
    {
        return 'List Moodle courses';
    }

    public function getHelp(): string
    {
        return 'Lists courses matching optional search criteria with configurable output fields and format.';
    }

    public function configure(CommandDefinition $definition): void
    {
        $this->handler->configureCommand($definition);
    }

    protected function getActiveHandler(): BaseHandler
    {
        return $this->handler;
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        return $this->handler->handle($input, $output);
    }

    private function resolveHandler(?MoodleVersion $moodleVersion, ClockInterface $clock): BaseHandler
    {
        if ($moodleVersion !== null && $moodleVersion->isAtLeast('5.2')) {
            return new CourseList52Handler($clock);
        }

        return new CourseList51Handler($clock);
    }
}
