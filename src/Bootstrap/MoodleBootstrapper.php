<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Bootstrap;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Handles Moodle require/login at the correct bootstrap level.
 *
 * Replaces the large switch/if block in the original moosh.php.
 */
final class MoodleBootstrapper
{
    private string $moodleDir;
    private MoodleVersion $version;
    private OutputInterface $output;

    public function __construct(string $moodleDir, MoodleVersion $version, OutputInterface $output)
    {
        $this->moodleDir = $moodleDir;
        $this->version = $version;
        $this->output = $output;
    }

    public function getMoodleDir(): string
    {
        return $this->moodleDir;
    }

    public function getVersion(): MoodleVersion
    {
        return $this->version;
    }

    /**
     * Bootstrap Moodle to the requested level.
     *
     * @param BootstrapLevel $level   How deep to bootstrap.
     * @param string|null    $user    Username to log in as (null = admin).
     * @param bool           $noLogin Skip user login entirely.
     */
    public function bootstrap(
        BootstrapLevel $level,
        ?string $user = null,
        bool $noLogin = false,
    ): void {
        if ($level === BootstrapLevel::None) {
            return;
        }

        if ($level === BootstrapLevel::DbOnly) {
            $this->bootstrapDbOnly();
            return;
        }

        $this->bootstrapFull($level, $user, $noLogin);
    }

    /**
     * Minimal bootstrap: just enough to talk to the DB.
     */
    private function bootstrapDbOnly(): void
    {
        global $CFG;

        // Evaluate config.php to populate $CFG without full Moodle bootstrap.
        $configFile = $this->moodleDir . '/config.php';
        if (!file_exists($configFile)) {
            throw new \RuntimeException("config.php not found in {$this->moodleDir}");
        }

        define('MOODLE_INTERNAL', true);
        define('ABORT_AFTER_CONFIG', true);
        define('CLI_SCRIPT', true);

        require_once($configFile);

        $libdir = $this->moodleDir . '/lib';
        require_once($libdir . '/dmllib.php');
        require_once($libdir . '/setuplib.php');
        require_once($libdir . '/moodlelib.php');
        require_once($libdir . '/weblib.php');

        setup_DB();
    }

    /**
     * Full (or config-only) bootstrap via config.php / lib/setup.php.
     */
    private function bootstrapFull(
        BootstrapLevel $level,
        ?string $user,
        bool $noLogin,
    ): void {
        global $CFG;

        // Set up server globals for non-CLI context.
        if ($level === BootstrapLevel::FullNoCli) {
            $_SERVER['REMOTE_ADDR'] = 'localhost';
            $_SERVER['SERVER_PORT'] = 80;
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP 1.1';
            $_SERVER['SERVER_SOFTWARE'] = 'PHP/' . phpversion() . ' Development Server';
            $_SERVER['REQUEST_URI'] = '/';
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        } else {
            if (!defined('CLI_SCRIPT')) {
                define('CLI_SCRIPT', true);
            }
        }

        if ($level === BootstrapLevel::Config) {
            if (!defined('ABORT_AFTER_CONFIG')) {
                define('ABORT_AFTER_CONFIG', true);
            }
        }

        if (!defined('MOODLE_INTERNAL')) {
            define('MOODLE_INTERNAL', true);
        }

        require_once($this->moodleDir . '/config.php');

        // Set up debugging.
        $CFG->debug = E_ALL;
        $CFG->debugdisplay = 1;
        @error_reporting(E_ALL);
        @ini_set('display_errors', '1');

        if (
            $level !== BootstrapLevel::Config
            && $level !== BootstrapLevel::FullNoAdminCheck
            && !$noLogin
        ) {
            $this->loginUser($user);
        }
    }

    /**
     * Log in as the given user (or admin if null).
     */
    private function loginUser(?string $user): void
    {
        global $DB;

        if ($user !== null) {
            $userRecord = $DB->get_record('user', ['username' => $user]);
            if (!$userRecord) {
                throw new \RuntimeException("User '$user' not found");
            }
        } else {
            $userRecord = get_admin();
            if (!$userRecord) {
                throw new \RuntimeException('No admin account found');
            }
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        \complete_user_login($userRecord);
    }
}
