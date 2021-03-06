<?php

namespace EvoSC\Modules\MusicClient;

use EvoSC\Classes\ChatCommand;
use EvoSC\Classes\Hook;
use EvoSC\Classes\Log;
use EvoSC\Classes\Module;
use EvoSC\Classes\RestClient;
use EvoSC\Classes\Server;
use EvoSC\Classes\Template;
use EvoSC\Classes\Timer;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;
use EvoSC\Modules\InputSetup\InputSetup;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class MusicClient extends Module implements ModuleInterface
{
    /**
     * @var Collection
     */
    private static $music;

    /**
     * @var stdClass
     */
    private static stdClass $song;

    /**
     * Called when the module is loaded
     *
     * @param string $mode
     * @param bool $isBoot
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (!config('music.url')) {
            return;
        }

        $promise = RestClient::getAsync(config('music.url'), [
            'connect_timeout' => 120
        ]);

        $promise->then(function (ResponseInterface $response) {
            if ($response->getStatusCode() != 200) {
                Log::warning('Failed to fetch music list.');

                return;
            }

            self::$music = collect(json_decode($response->getBody()->getContents()));
            $forcedUrl = Server::getForcedMusic()->url;
            if (!empty($forcedUrl)) {
                self::$song = self::$music->where('file', '=', urldecode(preg_replace('/^.+\?song=/', '', $forcedUrl)))->first();
            }

            Log::info('Music-library loaded successfully.');

            Hook::add('PlayerConnect', [self::class, 'playerConnect']);
            Hook::add('BeginMap', [self::class, 'setNextSong']);

            ChatCommand::add('/music', [self::class, 'cmdSearchMusic'], 'Open and search the music list.');

            InputSetup::add('reload_music_client', 'Reload music.', [self::class, 'reload'], 'F2', 'ms');
        }, function (RequestException $e) {
            Log::error('Failed to fetch music list: ' . $e->getMessage());
        });
    }

    public static function cmdSearchMusic(Player $player, $cmd, string $search = '')
    {
        Template::show($player, 'MusicClient.search-command', compact('search'), false, 20);
    }

    public static function setNextSong()
    {
        self::$song = self::$music->random(1)->first();
        Server::setForcedMusic(true, config('music.url') . '?song=' . urlencode(self::$song->file));

        if (self::$song) {
            Template::showAll('MusicClient.start-song', ['song' => json_encode(self::$song)], 60);
        }
    }

    public static function sendMusicLib(Player $player)
    {
        Template::show($player, 'MusicClient.list');

        if (isset(self::$music)) {
            $server = config('music.url');
            $chunks = self::$music->chunk(200);

            Template::show($player, 'MusicClient.send-music', [
                'server' => $server,
                'music' => $chunks,
            ], false, 60);
        } else {
            Timer::create(uniqid('sendMusicLib'), function () use ($player) {
                self::sendMusicLib($player);
            }, '10s');
        }
    }

    /**
     * Hook: PlayerConnect
     *
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        Template::show($player, 'MusicClient.music-client');
        self::sendMusicLib($player);

        if (isset(self::$song)) {
            $song = json_encode(self::$song);
            Template::show($player, 'MusicClient.start-song', compact('song'), false, 180);
        }
    }
}