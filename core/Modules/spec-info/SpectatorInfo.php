<?php

namespace esc\Modules;


use esc\Classes\Hook;
use esc\Classes\Template;
use esc\Models\Player;

class SpectatorInfo
{
    private static $specTargets;

    public function __construct()
    {
        self::$specTargets = collect();

        Hook::add('SpecStart', [self::class, 'specStart']);
        Hook::add('SpecStop', [self::class, 'specStop']);
    }

    public static function specStart(Player $player, Player $target)
    {
        self::$specTargets->put($player->Login, $target);
        self::updateWidget($target);
    }

    public static function specStop(Player $player)
    {
        self::$specTargets->put($player->Login, null);
    }

    public static function updateWidget(Player $player)
    {
        $spectatorLogins = self::$specTargets->filter(function (Player $target) use ($player) {
            return $target === $player;
        })->keys();

        $spectators = Player::whereIn('Login', $spectatorLogins)->get();

        Template::show($player, 'spec-info.widget', compact('spectators'));
    }
}