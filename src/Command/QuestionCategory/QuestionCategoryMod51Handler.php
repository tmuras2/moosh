<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Command\QuestionCategory;

use Moosh2\Command\BaseHandler;
use Moosh2\Output\ResultFormatter;
use Moosh2\Output\VerboseLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QuestionCategoryMod51Handler extends BaseHandler
{
    public function configureCommand(Command $command): void
    {
        $command
            ->addArgument('id', InputArgument::REQUIRED, 'Question category ID')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Set category name')
            ->addOption('info', null, InputOption::VALUE_REQUIRED, 'Set description')
            ->addOption('idnumber', null, InputOption::VALUE_REQUIRED, 'Set ID number')
            ->addOption('delete', null, InputOption::VALUE_NONE, 'Delete the category');
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        global $CFG, $DB;

        $verbose = new VerboseLogger($output);
        $runMode = $input->getOption('run');
        $format = $input->getOption('output');

        $catId = (int) $input->getArgument('id');
        $newName = $input->getOption('name');
        $newInfo = $input->getOption('info');
        $newIdnumber = $input->getOption('idnumber');
        $doDelete = $input->getOption('delete');

        require_once $CFG->libdir . '/questionlib.php';

        $cat = $DB->get_record('question_categories', ['id' => $catId]);
        if (!$cat) {
            $output->writeln("<error>Question category with ID $catId not found.</error>");
            return Command::FAILURE;
        }

        if (!$doDelete && $newName === null && $newInfo === null && $newIdnumber === null) {
            $output->writeln('<error>No modifications specified. Use --name, --info, --idnumber, or --delete.</error>');
            return Command::FAILURE;
        }

        if ($doDelete) {
            if (!$runMode) {
                $output->writeln("<info>Dry run — would delete question category '{$cat->name}' (ID=$catId) (use --run to execute).</info>");
                return Command::SUCCESS;
            }
            $manager = new \core_question\category_manager();
            $manager->delete_category($catId);
            $output->writeln("Deleted question category '{$cat->name}' (ID=$catId).");
            return Command::SUCCESS;
        }

        if (!$runMode) {
            $output->writeln("<info>Dry run — would modify question category '{$cat->name}' (ID=$catId) (use --run to execute):</info>");
            if ($newName !== null) { $output->writeln("  name → \"$newName\""); }
            if ($newInfo !== null) { $output->writeln("  info → \"$newInfo\""); }
            if ($newIdnumber !== null) { $output->writeln("  idnumber → \"$newIdnumber\""); }
            return Command::SUCCESS;
        }

        if ($newName !== null) { $cat->name = $newName; }
        if ($newInfo !== null) { $cat->info = $newInfo; }
        if ($newIdnumber !== null) { $cat->idnumber = $newIdnumber; }
        $DB->update_record('question_categories', $cat);

        $verbose->done("Modified question category (ID=$catId)");

        $headers = ['id', 'name', 'parent', 'idnumber'];
        $rows = [[$cat->id, $cat->name, $cat->parent, $cat->idnumber ?? '']];

        $formatter = new ResultFormatter($output, $format);
        $formatter->display($headers, $rows);

        return Command::SUCCESS;
    }
}
