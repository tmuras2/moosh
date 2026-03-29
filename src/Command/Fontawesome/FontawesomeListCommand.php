<?php
/**
 * moosh2 — Moodle Shell
 *
 * @copyright  2012 onwards Tomasz Muras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moosh2\Command\Fontawesome;

use Moosh2\Bootstrap\BootstrapLevel;
use Moosh2\Bootstrap\MoodleVersion;
use Moosh2\Command\BaseCommand;
use Moosh2\Command\BaseHandler;
use Moosh2\Output\VerboseLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List and search Font Awesome icons available in Moodle.
 *
 * Canonical name: fontawesome:list  |  Alias: fontawesome-list
 */
class FontawesomeListCommand extends BaseCommand
{
    protected BootstrapLevel $bootstrapLevel = BootstrapLevel::Full;

    private BaseHandler $handler;

    public function __construct(?MoodleVersion $moodleVersion)
    {
        $this->handler = $this->resolveHandler($moodleVersion);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fontawesome:list')
            ->setAliases(['fontawesome-list'])
            ->setDescription('List and search Font Awesome icons in Moodle')
            ->setHelp(
                "Lists Font Awesome icon mappings from Moodle's icon system.\n" .
                "Optionally filter by a search term matching Moodle icon name or FA icon class.\n\n" .
                "Examples:\n" .
                "  moosh fontawesome:list                   # list all icons\n" .
                "  moosh fontawesome:list search             # icons matching 'search'\n" .
                "  moosh fontawesome:list --component core   # only core icons"
            );

        $this->handler->configureCommand($this);
    }

    protected function getActiveHandler(): BaseHandler
    {
        return $this->handler;
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $verbose = new VerboseLogger($output);
        $verbose->step('Delegating to handler: ' . get_class($this->handler));
        return $this->handler->handle($input, $output);
    }

    private function resolveHandler(?MoodleVersion $moodleVersion): BaseHandler
    {
        if ($moodleVersion !== null && $moodleVersion->isAtLeast('5.2')) {
            return new FontawesomeList52Handler();
        }
        return new FontawesomeList51Handler();
    }
}
