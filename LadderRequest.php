<?php

/**
 * Class LadderRequest
 *
 * Fetches the ladder and profile data required to be displayed on the app, including the data
 * necessary to create challenges.
 */
class LadderRequest {

    /* The connection object. */
    private $connection;

    /**
     * Obtains a mysqli connection object via the Connection class.
     */
    function __construct() {
        require_once dirname(__FILE__) . "/Connection.php";
        $tennisDatabase = new Connection();
        $this->connection = $tennisDatabase->connect();
    }

    /**
     * Fetch ladder data for display in the ladder fragment. This includes returning necessary data
     * to view a players profile as well as create a challenge.
     */
    function getLadderProfileData(): array {
        /* Prepare statement. */
        $statementLadder = $this->connection->prepare
        (
            "SELECT playerid, email, fname, lname, (SELECT name FROM club WHERE player.clubid = club.clubid), 
                    elo, winstreak, hotstreak, matchesplayed, wins, losses, highestelo, clubchamp FROM player"
        );

        /* Execute statement and fetch results */
        $statementLadder->execute();
        $statementLadder->bind_result($playerID, $email, $fname, $lname, $clubName, $elo,
            $winStreak, $hotStreak, $matchesPlayed, $numWins, $numLosses, $highestElo, $clubChamp);

        /* Create the resulting array of ladder data */
        $players = array();
        while ($statementLadder->fetch()) {
            $player = array();
            $player["playerid"] = $playerID;
            $player["email"] = $email;
            $player["fname"] = $fname;
            $player["lname"] = $lname;
            $player["clubname"] = $clubName;
            $player["elo"] = $elo;
            $player["winstreak"] = $winStreak;
            $player["hotstreak"] = $hotStreak;
            $player["matchesplayed"] = $matchesPlayed;
            $player["wins"] = $numWins;
            $player["losses"] = $numLosses;
            $player["highestelo"] = $highestElo;
            $player["clubchamp"] = $clubChamp;
            array_push($players, $player);
        }
        $statementLadder->close();

        /* Append user achievements via a separate function call. */
        $appendAchievements = array();
        foreach ($players as $p) {
            $appended = array();
            $appended["playerid"] = $p["playerid"];
            $appended["fname"] = $p["fname"];
            $appended["lname"] = $p["lname"];
            $appended["clubname"] = $p["clubname"];
            $appended["elo"] = $p["elo"];
            $appended["winstreak"] = $p["winstreak"];
            $appended["hotstreak"] = $p["hotstreak"];
            $appended["matchesplayed"] = $p["matchesplayed"];
            $appended["wins"] = $p["wins"];
            $appended["losses"] = $p["losses"];
            $appended["highestelo"] = $p["highestelo"];
            $appended["clubchamp"] = $p["clubchamp"];
            $appended["achieved"] = (new PlayerRequest)->getUserAchievements($p["playerid"]);
            array_push($appendAchievements, $appended);
        }
        return $appendAchievements;
    }
}