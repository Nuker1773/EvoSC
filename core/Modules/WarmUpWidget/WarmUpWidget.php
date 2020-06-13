<?php


namespace EvoSC\Modules\WarmUpWidget;


use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\AccessRight;
use EvoSC\Models\Player;

class WarmUpWidget extends Module implements ModuleInterface
{
    private static int $round = 0;

    private static int $warmUpNb = 0;

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        AccessRight::createIfMissing('warm_up_skip', 'Lets you skip the warm-up phase.');

        Hook::add('WarmUpStart', [self::class, 'warmUpStart']);
        Hook::add('WarmUpEnd', [self::class, 'warmUpEnd']);
        Hook::add('Trackmania.WarmUp.StartRound', [self::class, 'warmUpStartRound']);

        self::$warmUpNb = Server::getModeScriptSettings()['S_WarmUpNb'];

        ManiaLinkEvent::add('warmup.skip', [self::class, 'skipWarmUp'], 'warm_up_skip');
    }

    public static function warmUpStart()
    {
        Template::showAll('WarmUpWidget.widget', [
            'warmupNb' => self::$warmUpNb,
            'round' => (self::$round = 0)
        ]);

        infoMessage('Warm-up started.')->setColor('f90')->setIcon(' ')->sendAll();
    }

    public static function warmUpStartRound()
    {
        Template::showAll('WarmUpWidget.widget', [
            'warmupNb' => self::$warmUpNb,
            'round' => ++self::$round
        ]);
    }

    public static function warmUpEnd()
    {
        infoMessage('Warm-up ended, ', secondary('starting play-loop.'))->setColor('f90')->setIcon(' ')->sendAll();
        Template::showAll('WarmUpWidget.widget', ['warmUpEnded' => true, 'warmupNb' => 0, 'round' => 0]);
    }

    public static function skipWarmUp(Player $player)
    {
        Server::triggerModeScriptEventArray('Trackmania.WarmUp.ForceStop', []);
        infoMessage($player, ' skips warm-up.')->setColor('f90')->sendAll();
        self::warmUpEnd();
    }

    public static function setWarmUpLimit(int $seconds)
    {
        $settings = Server::getModeScriptSettings();
        $settings['S_WarmUpDuration'] = $seconds;
        Server::setModeScriptSettings($settings);
    }
}