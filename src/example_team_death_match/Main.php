<?php

namespace example_team_death_match;

use bossbar_system\models\BossBar;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Color;
use team_game_system\model\Game;
use team_game_system\model\Score;
use team_game_system\model\Team;
use team_game_system\pmmp\event\AddedScoreEvent;
use team_game_system\pmmp\event\FinishedGameEvent;
use team_game_system\pmmp\event\PlayerJoinedGameEvent;
use team_game_system\pmmp\event\PlayerKilledPlayerEvent;
use team_game_system\pmmp\event\StartedGameEvent;
use team_game_system\pmmp\event\UpdatedGameTimerEvent;
use team_game_system\TeamGameSystem;

class Main extends PluginBase implements Listener
{
    private $teamDeathMatchGameIds = [];

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $teams = [
            Team::asNew("Red", new Color(255, 0, 0)),
            Team::asNew("Blue", new Color(0, 0, 255)),
        ];
        $maxScore = new Score(25);
        $map = TeamGameSystem::selectMap("Example", $teams);
        $game = Game::asNew($map, $teams, $maxScore, null, 300);

        TeamGameSystem::createGame($game);

        $this->teamDeathMatchGameIds[] = $game->getId();
    }

    public function onPlayerKilledPlayer(PlayerKilledPlayerEvent $event): void {
        $attacker = $event->getAttacker();
        $attackerData = TeamGameSystem::getPlayerData($attacker);
        TeamGameSystem::addScore($attackerData->getGameId(), $attackerData->getTeamId(), new Score(1));
    }

    public function onPlayerDeath(PlayerDeathEvent $event) {
        $event->setDrops([]);
    }

    public function onUpdatedTime(UpdatedGameTimerEvent $event): void {
        $gameId = $event->getGameId();
        $timeLimit = $event->getTimeLimit();
        $elapsedTime = $event->getElapsedTime();

        //BossBarの更新
        $playersData = TeamGameSystem::getGamePlayersData($gameId);
        foreach ($playersData as $playerData) {
            $player = $this->getServer()->getPlayer($playerData->getName());
            $bossBar = BossBar::get($player);
            $bossBar->updateTitle($player, "残り時間:" . ($timeLimit - $elapsedTime));
            $bossBar->updatePercentage($player, $elapsedTime / $timeLimit);
        }
    }

    public function onAddedScore(AddedScoreEvent $event): void {
        $gameId = $event->getGameId();
        if (in_array($gameId, $this->teamDeathMatchGameIds)) {
            $playersData = TeamGameSystem::getGamePlayersData($gameId);
            $game = TeamGameSystem::getGame($gameId);

            $redTeam = $game->getTeams()[0];
            $blueTeam = $game->getTeams()[1];

            foreach ($playersData as $playerData) {
                $player = $this->getServer()->getPlayer($playerData->getName());
                TeamDeathMatchScoreBoard::update($player, $game->getMap()->getName(), $redTeam->getScore()->getValue(), $blueTeam->getScore()->getValue());
            }
        }
    }

    public function onStartedGame(StartedGameEvent $event) {
        $gameId = $event->getGameId();
        $game = TeamGameSystem::getGame($gameId);

        $playersData = TeamGameSystem::getGamePlayersData($gameId);

        $bossBar = new BossBar("残り時間:" . ($game->getTimeLimit() - $game->getElapsedTime()), $game->getElapsedTime() / $game->getTimeLimit());
        foreach ($playersData as $playerData) {
            $player = $this->getServer()->getPlayer($playerData->getName());

            //スポーン地点をセット
            TeamGameSystem::setSpawnPoint($player);

            //テレポート
            $player->teleport($player->getSpawn());

            $player->addTitle("チームデスマッチ スタート");

            //Scoreboardのセット
            TeamDeathMatchScoreBoard::send($player, $game->getMap()->getName(), 0, 0);
            //BossBarのセット
            $bossBar->send($player);
        }
    }

    public function onFinishedGame(FinishedGameEvent $event): void {
        $game = $event->getGame();
        $playersData = $event->getPlayersData();

        foreach ($playersData as $playerData) {
            $player = $this->getServer()->getPlayer($playerData->getName());
            //テレポートやタイトル表示、リスポーン地点修正、ボスバースコアボード更新
        }
    }

    public function onJoinGame(PlayerJoinedGameEvent $event) {
        $player = $event->getPlayer();
        $gameId = $event->getGameId();

        //10人でスタート
        //$playersCount = TeamGameSystem::getGamePlayersData($gameId);
        //if ($playersCount === 10) {
        //    TeamGameSystem::startGame($this->getScheduler(), $gameId);
        //}
    }

    public function onRespawn(PlayerRespawnEvent $event) {
        $player = $event->getPlayer();
        $player->getInventory()->setContents([
            ItemFactory::get(ItemIds::WOODEN_SWORD, 0, 1),
            ItemFactory::get(ItemIds::APPLE, 0, 10),
        ]);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($sender instanceof Player) {
            switch ($label) {
                case "join":
                    $result = TeamGameSystem::joinGame($sender, $this->teamDeathMatchGameIds[0]);
                    $sender->sendMessage($result ? "参加しました" : "参加出来ませんでした");
                    break;
                case "start":
                    TeamGameSystem::startGame($this->getScheduler(), $this->teamDeathMatchGameIds[0]);
                    break;
            }
        }
        return true;
    }
}