<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Command\Course;

use Moosh2\Output\ResultFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shared helpers for version-specific course:list handlers.
 */
trait CourseListHelperTrait
{
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
        InputInterface $input,
        OutputInterface $output,
        bool $idOnly,
        string $visible,
        ?array $fields,
    ): void {
        $headers = [];
        $rows = [];
        $headersBuilt = false;

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
                if (!$headersBuilt) {
                    $headers[] = $field;
                }
                $row[] = $value;
            }
            $rows[] = $row;
            $headersBuilt = true;
        }

        if ($idOnly) {
            return;
        }

        $format = $input->getOption('output');
        $formatter = new ResultFormatter($output, $format);
        $formatter->display($headers, $rows);
    }
}
