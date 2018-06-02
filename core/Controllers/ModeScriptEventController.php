<?php

namespace esc\Controllers;


use esc\Classes\Hook;
use esc\Classes\Log;
use esc\Models\Player;

class ModeScriptEventController
{
    public static function handleModeScriptCallbacks($modescriptCallbackArray)
    {
        $callback  = $modescriptCallbackArray[0];
        $arguments = $modescriptCallbackArray[1];

        switch ($callback) {
            case 'Trackmania.Scores':
                self::tmScores($arguments);
                break;

            case 'Trackmania.Event.GiveUp':
                self::tmGiveUp($arguments);
                break;

            case 'Trackmania.Event.WayPoint':
                self::tmWayPoint($arguments);
                break;

            case 'Trackmania.Event.StartCountdown':
                self::tmStartCountdown($arguments);
                break;

            case 'Trackmania.Event.StartLine':
                self::tmStartLine($arguments);
                break;

            case 'Trackmania.Event.Stunt':
                self::tmStunt($arguments);
                break;

            case 'Trackmania.Event.OnPlayerAdded':
                self::tmPlayerConnect($arguments);
                break;

            case 'Trackmania.Event.OnPlayerRemoved':
                self::tmPlayerLeave($arguments);
                break;

            default:
                Log::logAddLine('ScriptCallback', "Calling unhandled $callback", false);
                break;
        }

        Hook::fire($callback, $arguments);
    }

    static function tmScores($arguments)
    {
        Hook::fire('ShowScores', $arguments);
    }

    static function tmGiveUp($arguments)
    {
        $playerLogin = json_decode($arguments[0])->login;
        $player      = Player::find($playerLogin);

        Hook::fire('PlayerFinish', $player, 0, "");
    }

    static function tmWayPoint($arguments)
    {
        $wayPoint = json_decode($arguments[0]);

        $player = Player::find($wayPoint->login);
        $map    = MapController::getCurrentMap();

        $totalCps = $map->NbCheckpoints;

        //checkpoint passed
        Hook::fire('PlayerCheckpoint',
            $player,
            $wayPoint->laptime,
            ceil($wayPoint->checkpointinrace / $totalCps),
            count($wayPoint->curlapcheckpoints) - 1
        );

        //player finished
        if ($wayPoint->isendlap) {
            Hook::fire('PlayerFinish',
                $player,
                $wayPoint->laptime,
                self::cpArrayToString($wayPoint->curlapcheckpoints)
            );
        }
    }

    static function tmStartCountdown($arguments)
    {
        $playerLogin = json_decode($arguments[0])->login;
        $player      = Player::find($playerLogin);
        Hook::fire('PlayerStartCountdown', $player);
    }

    static function tmStartLine($arguments)
    {
        $playerLogin = json_decode($arguments[0])->login;
        $player      = Player::find($playerLogin);
        Hook::fire('PlayerStartLine', $player);
    }

    static function tmStunt($arguments)
    {
        //ignore stunts for now
    }

    static function tmPlayerConnect($arguments)
    {
        $playerData = json_decode($arguments[0]);

        //string Login, bool IsSpectator
        if (Player::whereLogin($playerData->login)->get()->isEmpty()) {
            $player = Player::create(['Login' => $playerData->login]);
        } else {
            $player = Player::find($playerData->login);
        }

        Hook::fire('PlayerConnect', $player);
    }

    static function tmPlayerLeave($arguments)
    {
        $playerData = json_decode($arguments[0]);
        $player     = Player::find($playerData->login);

        Hook::fire('PlayerLeave', $player);
    }

    /**
     * Convert cp array to comma separated string
     * @param array $cps
     * @return string
     */
    private static function cpArrayToString(array $cps)
    {
        return implode(',', $cps);
    }
}