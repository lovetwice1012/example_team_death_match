<?php


namespace example_team_death_match;


use pocketmine\Player;
use pocketmine\utils\TextFormat;
use scoreboard_system\models\Score;
use scoreboard_system\models\Scoreboard;
use scoreboard_system\models\ScoreboardSlot;
use scoreboard_system\models\ScoreSortType;

class TeamDeathMatchScoreBoard extends Scoreboard
{
    private static function create(string $mapName, int $redTeamScore, int $blueTeamScore): Scoreboard {
        $slot = ScoreboardSlot::sideBar();
        $scores = [
            new Score($slot, "====TeamDeathMatch====", 0, 0),
            new Score($slot, "Map:" . $mapName, 1, 1),
            new Score($slot, TextFormat::RED . "RedTeam:" . $redTeamScore, 2, 2),
            new Score($slot, TextFormat::BLUE . "BlueTeam:" . $blueTeamScore, 3, 3),
        ];
        return parent::__create($slot, "Server Name", $scores, ScoreSortType::smallToLarge());
    }

    static function send(Player $player, string $mapName, int $redTeamScore, int $blueTeamScore) {
        $scoreboard = self::create($mapName, $redTeamScore, $blueTeamScore);
        parent::__send($player, $scoreboard);
    }

    static function update(Player $player, string $mapName, int $redTeamScore, int $blueTeamScore) {
        $scoreboard = self::create($mapName, $redTeamScore, $blueTeamScore);
        parent::__update($player, $scoreboard);
    }
}