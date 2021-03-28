<?php

/**
 * Class PlayerRequest
 *
 * Database queries corresponding to player requests. Allows the user to signup and login, as well as
 * post their achievements to the database.
 */
class PlayerRequest {

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
     * Providing the email exists, check the provided hashed password against the hashed password stored
     * in the database. A maximum of 1 user can be retrieved, as the email is ensured to be unique upon signup.
     */
    function login($email, $password): bool {
        /* Prepare statement. */
        $statementLogin = $this->connection->prepare
        (
            "SELECT password FROM player WHERE email = ?"
        );
        $statementLogin->bind_param("s", $email);

        /* Execute and fetch results. */
        $statementLogin->execute();
        $statementLogin->bind_result($hashedPassword);
        $statementLogin->fetch();
        $statementLogin->close();

        /* Verify the hashed passwords match. */
        if (password_verify($password, $hashedPassword)) {
            return true;
        }
        return false;
    }

    /**
     * Attempt to create a player using the provided parameters. If the email is
     * already in use, alert the user. The password is hashed prior to database entry.
     */
    function createPlayer($email, $password, $contactNo, $fname, $lname, $clubName): int {
        /* Hash the password and prepare the statement. */
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $statementSignup = $this->connection->prepare
        (
            "INSERT INTO player (email, password, contactno, fname, lname, clubid) 
                    VALUES (?, ?, ?, ?, ?, (SELECT clubid from club WHERE name = ?))"
        );
        $statementSignup->bind_param("ssssss", $email, $hashedPassword, $contactNo, $fname, $lname, $clubName);

        /* Execute the statement and return the error code, where 0 indicates success. */
        $statementSignup->bind_param("ssssss", $email, $hashedPassword, $contactNo, $fname, $lname, $clubName);
        $statementSignup->execute();
        $errorNo = $statementSignup->errno;
        $statementSignup->close();
        return $errorNo;
    }

    /**
     * Return a list of valid clubs for signup.
     */
    function getClubs(): array {
        /* Prepare statement. */
        $statementClubs = $this->connection->prepare
        (
            "SELECT name FROM club"
        );
        $statementClubs->execute();
        $statementClubs->bind_result($club);
        $clubs = array();

        /* Execute statement and return clubs array. */
        while ($statementClubs->fetch()) {
            array_push($clubs, $club);
        }
        $statementClubs->close();
        return $clubs;
    }

    /**
     * Create a new club that can be selected upon user signup.
     */
    function createClub($name, $address): bool {
        /* Prepare and execute statement. */
        $statementCreateClub = $this->connection->prepare
        (
            "INSERT INTO club (name, address) VALUES (?,?)"
        );
        $statementCreateClub->bind_param("ss", $name, $address);
        if ($statementCreateClub->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Upon a successful login attempt, this function is used to return
     * the necessary user data to perform tasks during the login session.
     */
    function getPlayerData($email): array {
        /* Prepare statement. */
        $statementGetPlayerData = $this->connection->prepare
        (
            "SELECT playerid, contactno, fname, lname, (SELECT name FROM club WHERE player.clubid = club.clubid), 
                    elo, winstreak, hotstreak, matchesplayed, wins, losses, highestelo, clubchamp FROM player WHERE email = ?"
        );
        $statementGetPlayerData->bind_param("s", $email);

        /* Execute player data statement and fetch the results. */
        $statementGetPlayerData->execute();
        $statementGetPlayerData->bind_result(
            $playerID, $contactNo, $fname, $lname, $clubName, $elo, $winStreak,
            $hotStreak, $matchesPlayed, $numWins, $numLosses, $highestElo, $clubChamp);
        $statementGetPlayerData->fetch();
        $statementGetPlayerData->close();

        /* Create the resulting user data array. */
        $player = array();
        $player["playerid"] = $playerID;
        $player["email"] = $email;
        $player["contactno"] = $contactNo;
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
        $player["achieved"] = $this->getUserAchievements($playerID);  // Fetch player achievements.

        return $player;
    }

    /**
     * Obtain a list of achievements with names and descriptions on the database.
     */
    function getAchievementList(): array {
        $statementGetAchievements = $this->connection->prepare
        (
            "SELECT * FROM achievement"
        );
        $statementGetAchievements->execute();
        $statementGetAchievements->bind_result($achievementID, $achievementName, $achievementDescription);
        $achievements = array();
        while ($statementGetAchievements->fetch()) {
            $achievement["achievementid"] = strval($achievementID);
            $achievement["achievementname"] = $achievementName;
            $achievement["achievementdescription"] = $achievementDescription;
            array_push($achievements, $achievement);
        }
        return $achievements;
    }

    /**
     * Obtain a list of achievements for a given user.
     */
    function getUserAchievements($playerID): array {
        $statementGetAchievements = $this->connection->prepare
        (
            "SELECT achievementid FROM player_achievement WHERE playerid = ?"
        );
        $statementGetAchievements->bind_param("i", $playerID);
        $statementGetAchievements->execute();
        $statementGetAchievements->bind_result($achievementID);
        $ownedAchievements = array();
        while ($statementGetAchievements->fetch()) {
            array_push($ownedAchievements, $achievementID);
        }
        $statementGetAchievements->close();
        return $ownedAchievements;
    }

    /**
     * Post a user-obtained achievement to the player_achievement database
     */
    function postAchievement($achievementID, $playerID): bool {
        $achievementID = intval($achievementID);
        $playerID = intval($playerID);
        $statementAchievement = $this->connection->prepare
        (
            "INSERT INTO player_achievement (achievementid, playerid) VALUES (?, ?)"
        );
        $statementAchievement->bind_param("ii", $achievementID, $playerID);
        if ($statementAchievement->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Operates similarly to get_challenges. This function instead returns data related to completed
     * matches, where a "match" is a challenge that has been accepted and had its score reported.
     */
    function getMatchHistory($playerID): array {
        /* Construct a list of challenge ID's an initiation status'. -1 indicates that a
        result has not been posted and the match not yet played. */
        $statementGetIDList = $this->connection->prepare
        (
            "SELECT challengeid, didwin FROM player_challenge WHERE playerid = ? AND didwin != -1"
        );

        /* Prepare and execute statement. */
        $playerID = intval($playerID);
        $statementGetIDList->bind_param("i", $playerID);
        $statementGetIDList->execute();
        $statementGetIDList->bind_result($challengeID, $didWin);
        $matchList = array();
        while ($statementGetIDList->fetch()) {
            $locatedMatch = array();
            $locatedMatch["challengeid"] = $challengeID;
            $locatedMatch["didwin"] = $didWin;
            array_push($matchList, $locatedMatch);
        }

        /* For each identified match, fetch relevant data. */
        $matches = array(); // The final matches array to be returned.
        foreach ($matchList as $m) {
            /* Statement to fetch opponent data, != playerID refers to the opponent. */
            $statementGetOpponent = $this->connection->prepare
            (
                "SELECT playerid, fname, lname FROM player WHERE playerid = (SELECT playerid 
                        FROM player_challenge WHERE challengeid = ? AND playerid != ?)"
            );

            /* Statement to fetch match metadata. */
            $statementGetChallengeData = $this->connection->prepare
            (
                "SELECT date, score FROM challenge WHERE challengeid = ?"
            );

            /* Prepare and execute the opponent data statement */
            $statementGetOpponent->bind_param("ii", $m["challengeid"], $playerID);
            $statementGetOpponent->execute();
            $statementGetOpponent->bind_result($opponentID, $fname, $lname);
            $statementGetOpponent->fetch();
            $statementGetOpponent->close();

            /* Prepare and execute the match data statement. */
            $statementGetChallengeData->bind_param("i", $m["challengeid"]);
            $statementGetChallengeData->execute();
            $statementGetChallengeData->bind_result($date, $score);
            $statementGetChallengeData->fetch();
            $statementGetChallengeData->close();

            $match = array();   // Array to hold a single match.

            /* Append fetched data to the matches array. */
            $match["challengeid"] = $m["challengeid"];
            $match["didwin"] = $m["didwin"];
            $match["opponentid"] = $opponentID;
            $match["fname"] = $fname;
            $match["lname"] = $lname;
            $match["date"] = $date;
            $match["score"] = $score;
            array_push($matches, $match);
        }
        return $matches;
    }

    /**
     * Delete player with associated ID.
     */
    function deletePlayer($playerID): bool {
        $statementDeletePlayer = $this->connection->prepare
        (
            "DELETE FROM player WHERE playerid = ?"
        );
        $statementDeletePlayer->bind_param("i", $playerID);
        if ($statementDeletePlayer->execute()) {
            return true;
        }
        return false;
    }
}