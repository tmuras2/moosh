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
            ->addOption('fields', 'f', InputOption::VALUE_REQUIRED, 'Comma-separated list of fields to show')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output format: csv, tab', 'csv');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        global $CFG, $DB;

        require_once $CFG->dirroot . '/course/lib.php';

        $showIdnumber = $input->getOption('idnumber');
        $idOnly = $input->getOption('id-only');
        $categoryId = $input->getOption('category');
        $visible = $input->getOption('visible');
        $empty = $input->getOption('empty');
        $fieldsRaw = $input->getOption('fields');
        $format = $input->getOption('output');
        $searchFragments = $input->getArgument('search');

        $fields = $fieldsRaw ? array_map('trim', explode(',', $fieldsRaw)) : null;

        // Build query.
        $select = ['c.id', 'c.category'];
        if ($showIdnumber) {
            $select[] = 'c.idnumber';
        }
        if ($empty === 'yes' || $empty === 'no') {
            $select[] = 'COUNT(c.id) AS modules';
        }
        $select[] = 'c.shortname';
        $select[] = 'c.fullname';
        $select[] = 'c.visible';

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM {course} c';

        if ($empty === 'yes' || $empty === 'no') {
            $sql .= ' LEFT JOIN {course_modules} m ON c.id = m.course';
        }

        $where = ["'1' = '1'"];
        $params = [];

        if ($categoryId !== null) {
            $category = \core_course_category::get((int) $categoryId);
            $categoryIds = $this->getCategoryIds($category);
            [$inSql, $inParams] = $DB->get_in_or_equal($categoryIds);
            $where[] = "c.category $inSql";
            $params = array_merge($params, $inParams);
        }

        if ($searchFragments) {
            $where[] = '(' . implode(' ', $searchFragments) . ')';
        }

        $sql .= ' WHERE ' . implode(' AND ', $where);

        if ($empty === 'yes') {
            $sql .= ' GROUP BY c.id HAVING COUNT(c.id) < 2';
        } elseif ($empty === 'no') {
            $sql .= ' GROUP BY c.id HAVING COUNT(c.id) > 1';
        }

        if ($output->isVerbose()) {
            $output->writeln("SQL: $sql");
            $output->writeln('Params: ' . var_export($params, true));
        }

        $courses = $DB->get_records_sql($sql, $params ?: null);

        // Secondary filter for truly empty courses (no non-empty sections).
        if ($empty === 'yes') {
            $sectionSql = "SELECT COUNT(*) AS c FROM {course_sections} WHERE course = ? AND summary <> ''";
            foreach ($courses as $id => $course) {
                $sections = $DB->get_record_sql($sectionSql, [$course->id]);
                if ($sections->c > 0) {
                    unset($courses[$id]);
                }
            }
        }

        $this->displayCourses($courses, $output, $idOnly, $visible, $fields, $format);

        return self::SUCCESS;
    }

    /**
     * Recursively collect category IDs.
     *
     * @return int[]
     */
    private function getCategoryIds(\core_course_category $category): array
    {
        $ids = [$category->id];
        foreach ($category->get_children() as $child) {
            $ids = array_merge($ids, $this->getCategoryIds($child));
        }
        return $ids;
    }

    /**
     * Render the course list to the console.
     */
    private function displayCourses(
        array $courses,
        OutputInterface $output,
        bool $idOnly,
        string $visible,
        ?array $fields,
        string $format,
    ): void {
        $header = [];
        $rows = [];
        $headerBuilt = false;

        foreach ($courses as $course) {
            if ($visible === 'yes' && !$course->visible) {
                continue;
            }
            if ($visible === 'no' && $course->visible) {
                continue;
            }

            if ($idOnly) {
                $output->writeln((string) $course->id);
                continue;
            }

            $row = [];
            foreach ($course as $field => $value) {
                if ($fields !== null && !in_array($field, $fields, true)) {
                    continue;
                }
                if (!$headerBuilt) {
                    $header[] = $field;
                }
                $row[] = $value;
            }
            $rows[] = $row;
            $headerBuilt = true;
        }

        if ($idOnly) {
            return;
        }

        // Prepend header row.
        if ($header) {
            array_unshift($rows, $header);
        }

        $separator = $format === 'tab' ? "\t" : ',';
        foreach ($rows as $row) {
            if ($format === 'csv') {
                $row = array_map(fn($v) => '"' . $v . '"', $row);
            }
            $output->writeln(implode($separator, $row));
        }
    }
}
