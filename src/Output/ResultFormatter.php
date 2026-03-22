<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Output;

use Moosh2\Console\OutputInterface;

/**
 * Shared output formatter for command results.
 *
 * Renders tabular data (headers + rows) in table, CSV, or JSON format.
 */
class ResultFormatter
{
    public function __construct(
        private readonly OutputInterface $output,
        private readonly string $format = 'table',
    ) {
    }

    /**
     * Render tabular data in the configured format.
     *
     * @param string[] $headers Column names.
     * @param array[]  $rows    Array of row arrays (values in same order as headers).
     */
    public function display(array $headers, array $rows): void
    {
        match ($this->format) {
            'json' => $this->renderJson($headers, $rows),
            'csv' => $this->renderCsv($headers, $rows),
            'oneline' => $this->renderOneline($headers, $rows),
            default => $this->renderTable($headers, $rows),
        };
    }

    private function renderTable(array $headers, array $rows): void
    {
        if ($headers === []) {
            return;
        }

        // Calculate column widths.
        $widths = array_map('mb_strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, mb_strlen((string) $cell));
            }
        }

        $separator = '+' . implode('+', array_map(
            fn(int $w) => str_repeat('-', $w + 2),
            $widths,
        )) . '+';

        $formatRow = function (array $cells) use ($widths): string {
            $parts = [];
            foreach ($cells as $i => $cell) {
                $parts[] = ' ' . str_pad((string) $cell, $widths[$i]) . ' ';
            }
            return '|' . implode('|', $parts) . '|';
        };

        $this->output->writeln($separator);
        $this->output->writeln($formatRow($headers));
        $this->output->writeln($separator);

        foreach ($rows as $row) {
            $this->output->writeln($formatRow($row));
        }

        $this->output->writeln($separator);
    }

    private function renderCsv(array $headers, array $rows): void
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $this->output->write(stream_get_contents($stream));
        fclose($stream);
    }

    private function renderOneline(array $headers, array $rows): void
    {
        $values = array_map(fn(array $row) => $row[0] ?? '', $rows);
        $this->output->writeln(implode(' ', $values));
    }

    private function renderJson(array $headers, array $rows): void
    {
        $data = [];
        foreach ($rows as $row) {
            $data[] = array_combine($headers, $row);
        }
        $this->output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
