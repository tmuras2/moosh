<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Bootstrap;

/**
 * Locates a Moodle installation by walking up the directory tree.
 *
 * Equivalent to the original find_top_moodle_dir() / is_top_moodle_dir().
 */
final class MoodlePathResolver
{
    private const MAX_DEPTH = 10;

    /**
     * Walk up from $startDir looking for a Moodle root (config.php + version.php).
     *
     * @return string|null  Absolute path to Moodle root, or null if not found.
     */
    public function resolve(?string $startDir = null): ?string
    {
        $dir = $startDir ?? getcwd();
        if ($dir === false) {
            return null;
        }

        for ($i = 0; $i <= self::MAX_DEPTH; $i++) {
            if ($this->isMoodleRoot($dir)) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                // Reached filesystem root.
                break;
            }
            $dir = $parent;
        }

        return null;
    }

    /**
     * Check whether a directory looks like a Moodle root.
     */
    private function isMoodleRoot(string $dir): bool
    {
        return file_exists($dir . '/config.php')
            && file_exists($dir . '/version.php')
            && file_exists($dir . '/lib/moodlelib.php');
    }
}
