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
     * Read space-separated course IDs from stdin.
     *
     * @return int[]|null  Array of IDs when --stdin is active, null otherwise.
     */
    private function readStdinIds(InputInterface $input): ?array
    {
        if (!$input->getOption('stdin')) {
            return null;
        }

        $raw = file_get_contents('php://stdin');
        $ids = array_filter(
            array_map('intval', preg_split('/\s+/', trim($raw))),
            fn(int $id) => $id > 0,
        );

        return $ids;
    }

    /**
     * Filter courses to only those whose IDs appear in the given list.
     *
     * @param int[]|null $stdinIds
     */
    private function filterByStdinIds(array $courses, ?array $stdinIds): array
    {
        if ($stdinIds === null) {
            return $courses;
        }

        $allowed = array_flip($stdinIds);

        return array_filter(
            $courses,
            fn(object $course) => isset($allowed[(int) $course->id]),
        );
    }

    /**
     * Render the course list to the console.
     */
    private function displayCourses(
        array $courses,
        InputInterface $input,
        OutputInterface $output,
        bool $idOnly,
        ?bool $visible,
        ?array $fields,
    ): void {
        $headers = [];
        $rows = [];
        $headersBuilt = false;

        foreach ($courses as $course) {
            if ($visible === true && !$course->visible) {
                continue;
            }
            if ($visible === false && $course->visible) {
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
