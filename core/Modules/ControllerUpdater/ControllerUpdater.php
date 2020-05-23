<?php


namespace EvoSC\Modules\ControllerUpdater;


use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\Module;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Timer;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use Psr\Http\Message\ResponseInterface;
use ZipArchive;

class ControllerUpdater extends Module implements ModuleInterface
{
    private static bool $updateAvailable = false;
    private static string $latestVersion = '';

    public static function start(string $mode, bool $isBoot = false)
    {
        Hook::add('PlayerConnect', [self::class, 'playerConnect']);

        ChatCommand::add('//update-evosc', [self::class, 'cmdUpdateEvoSC'], 'Updates EvoSC and restarts it.', 'ma');

        self::$latestVersion = getEscVersion();
        self::checkForUpdates();
    }

    /**
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        if (self::$updateAvailable && $player->hasAccess('ma')) {
            infoMessage('New ', secondary('EvoSC v' . self::$latestVersion), ' available, type ', secondary('//update-evosc'), ' to update.')->send($player);
        }
    }

    /**
     * @param Player $player
     * @param $cmd
     */
    public static function cmdUpdateEvoSC(Player $player, $cmd)
    {
        infoMessage('Updating ', secondary('EvoSC'), ' to the latest version.')->send($player);

        $promise = RestClient::getAsync('https://evotm.com/api/evosc/latest?branch=' . config('evosc.release'));

        $promise->then(function (ResponseInterface $response) use ($player) {
            if ($response->getStatusCode() == 200) {
                file_put_contents(coreDir('../update.zip'), $response->getBody());

                $zip = new ZipArchive;
                $res = $zip->open(coreDir('../update.zip'));
                if ($res === TRUE) {
                    $zip->extractTo('.');
                    $zip->close();

                    successMessage(secondary('EvoSC'), ' successfully updated.')->send($player);
                    unlink(coreDir('../update.zip'));
                    restart_evosc();
                } else {
                    dangerMessage('Failed to update ', secondary('EvoSC'))->send($player);
                }
            }
        });
    }

    /**
     *
     */
    public static function checkForUpdates()
    {
        if (!self::$updateAvailable) {
            $promise = RestClient::getAsync('https://evotm.com/api/evosc/version?branch=' . config('evosc.release'));

            $promise->then(function (ResponseInterface $response) {
                if ($response->getStatusCode() == 200) {
                    $latestVersion = $response->getBody()->getContents();

                    if ($latestVersion != '-1' && $latestVersion > getEscVersion()) {
                        self::$latestVersion = $latestVersion;
                        Log::cyan('EvoSC update available.');
                        infoMessage('New ', secondary('EvoSC v' . $latestVersion), ' available, type ', secondary('//update-evosc'), ' to update.')->send(accessPlayers('ma'));
                        self::$updateAvailable = true;
                    }
                }
            });
        }

        Timer::create('evosc_update_checker', [self::class, 'checkForUpdates'], '1h');
    }
}