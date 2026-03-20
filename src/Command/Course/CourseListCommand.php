<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Command\Course;

use Moosh2\Bootstrap\BootstrapLevel;
use Moosh2\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List courses matching optional search criteria.
 *
 * Replaces Moosh\Command\Moodle39\Course\CourseList.
 * Canonical name: course:list  |  Alias: course-list
 */
class CourseListCommand extends BaseCommand
{
    protected BootstrapLevel $bootstrapLevel = BootstrapLevel::Full;

    protected function configure(): void
    {
        $this
            ->setName('course:list')
            ->setAliases(['course-list'])
            ->setDescription('List Moodle courses')
            ->setHelp('Lists courses matching optional search criteria with configurable output fields and format.')
            ->addArgument(
                'search',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'SQL WHERE fragments to filter courses',
            )
            ->addOption('idnumber', null, InputOption::VALUE_NONE, 'Include the idnumber column')
            ->addOption('id-only', 'i', InputOption::VALUE_NONE, 'Display only course IDs')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'Limit to courses in this category ID (includes subcategories)')
            ->addOption('visible', null, InputOption::VALUE_REQUIRED, 'Filter by visibility: all, yes, no', 'all')
            ->addOption('empty', null, InputOption::VALUE_REQUIRED, 'Filter by empty courses: all, yes, no', 'all')
            ->addOption('fields', 'f', InputOption::VALUE_REQUIRED, 'Comma-separated list of fields to show');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        /** @var \Moosh2\Application $app */
        $app = $this->getApplication();
        $version = $app->getBootstrapper($input, $output)->getVersion();

        $handler = $version->isAtLeast('5.2')
            ? new CourseList52Handler()
            : new CourseList51Handler();

        return $handler->handle($input, $output);
    }
}
