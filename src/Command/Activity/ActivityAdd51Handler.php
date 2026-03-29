<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Command\Activity;

use Moosh2\Command\BaseHandler;
use Moosh2\Output\ResultFormatter;
use Moosh2\Output\VerboseLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * activity:add implementation for Moodle 5.1.
 */
class ActivityAdd51Handler extends BaseHandler
{
    public function configureCommand(Command $command): void
    {
        $command
            ->addArgument('type', InputArgument::REQUIRED, 'Activity module type (e.g. assign, forum, quiz, resource, url, page)')
            ->addArgument('course', InputArgument::REQUIRED, 'Course ID')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Activity name')
            ->addOption('section', 's', InputOption::VALUE_REQUIRED, 'Section number', '1')
            ->addOption('idnumber', null, InputOption::VALUE_REQUIRED, 'Activity ID number');
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        global $CFG, $DB;

        $verbose = new VerboseLogger($output);
        $format = $input->getOption('output');
        $runMode = $input->getOption('run');

        $type = $input->getArgument('type');
        $courseId = (int) $input->getArgument('course');
        $name = $input->getOption('name');
        $section = (int) $input->getOption('section');
        $idnumber = $input->getOption('idnumber');

        // Validate module type exists.
        $module = $DB->get_record('modules', ['name' => $type]);
        if (!$module) {
            $output->writeln("<error>Unknown activity type: $type</error>");
            return Command::FAILURE;
        }

        // Validate course exists.
        $course = $DB->get_record('course', ['id' => $courseId]);
        if (!$course) {
            $output->writeln("<error>Course with ID $courseId not found.</error>");
            return Command::FAILURE;
        }

        if (!$runMode) {
            $displayName = $name ?? "New $type";
            $output->writeln("<info>Dry run — would create $type activity \"$displayName\" in course $courseId section $section (use --run to execute).</info>");
            return Command::SUCCESS;
        }

        $verbose->step('Loading Moodle libraries');
        require_once $CFG->dirroot . '/course/lib.php';
        require_once $CFG->libdir . '/phpunit/classes/util.php';
        require_once $CFG->dirroot . '/lib/testing/generator/lib.php';

        $verbose->step("Creating $type activity in course $courseId");

        $moduleData = [
            'course' => $courseId,
            'section' => $section,
        ];

        if ($name !== null) {
            $moduleData['name'] = $name;
        }

        if ($idnumber !== null) {
            $moduleData['idnumber'] = $idnumber;
        }

        $generator = \testing_util::get_data_generator();
        $instance = $generator->create_module($type, (object) $moduleData);

        $verbose->done("Created $type with course module ID {$instance->cmid}");

        $headers = ['cmid', 'module', 'instance', 'course', 'section'];
        $rows = [[$instance->cmid, $type, $instance->id, $courseId, $section]];

        $formatter = new ResultFormatter($output, $format);
        $formatter->display($headers, $rows);

        return Command::SUCCESS;
    }
}
