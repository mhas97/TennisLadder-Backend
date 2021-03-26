<?php /** @noinspection ALL */

// [REF: https://www.simplifiedcoding.net/android-mysql-tutorial-to-perform-basic-crud-operation/#Android-MySQL-Tutorial]

/**
 * Class Action
 */
class Action
{

    private $connection;

    /**
     * Obtains a mysqli connection object via the Connection class.
     */
    function __construct()
    {
        require_once dirname(__FILE__) . "/Connection.php";
        $tennisDatabase = new Connection();
        $this->connection = $tennisDatabase->connect();
    }

    /**
     * Providing the email exists, checks the provided hashed password against
     * the hashed password stored in the database. A maximum of 1 user can be
     * retrieved, as the email is ensured to be unique upon signup.
     */
    function login($email, $password)
    {
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
    function create_player($email, $password, $contactNo, $fname, $lname, $clubName)
    {
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
     * Return a list of valid clubs.
     */
    function get_clubs()
    {
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
    function create_club($name, $address)
    {
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
    function get_player_data($email)
    {
        /* Prepare statement. */
        $statementGetPlayerData = $this->connection->prepare
        (
            "SELECT playerid, contactno, fname, lname, (SELECT name FROM club WHERE player.clubid = club.clubid), 
                    elo, winstreak, hotstreak, matchesplayed, wins, losses, highestelo, clubchamp FROM player WHERE email = ?"
        );
        $statementGetPlayerData->bind_param("s", $email);

        /* Execute statement and fetch the results. */
        $statementGetPlayerData->execute();
        $statementGetPlayerData->bind_result($playerID, $contactNo, $fname, $lname,
            $clubName, $elo, $winStreak, $hotStreak, $matchesPlayed, $numWins, $numLosses, $highestElo, $clubChamp);
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
        return $player;
    }

    /**
     * Fetch ladder data for display in the ladder fragment. This
     * includes returning necessary data to view a players profile
     * as well as create a challenge.
     */
    function get_ladder_profile_data()
    {
        /* Prepare statement. */
        $statementLadder = $this->connection->prepare
        (
            "SELECT playerid, fname, lname, (SELECT name FROM club WHERE player.clubid = club.clubid), 
                    elo, winstreak, hotstreak, matchesplayed, wins, losses, highestelo, clubchamp FROM player"
        );

        /* Execute statement and fetch results */
        $statementLadder->execute();
        $statementLadder->bind_result($playerID, $fname, $lname, $clubName, $elo,
            $winStreak, $hotStreak, $matchesPlayed, $numWins, $numLosses, $highestElo, $clubChamp);

        /* Create the resulting array of ladder data */
        $players = array();
        while ($statementLadder->fetch()) {
            $player = array();
            $player["playerid"] = $playerID;
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
        return $players;
    }

    /**
     * @param $time String provided in unix time in seconds, this makes conversion for database storage easier.
     * Create an entry in the challenge table containing challenge metadata.
     */
    function create_challenge($clubName, $date, $time)
    {
        $date = intval($date);
        date_default_timezone_set("Europe/London");     // Ensure correct timezone.
        $mysqlDate = date("Y-m-d", $date);      // Format date for database entry.

        /* Prepare and execute statement. */
        $statementChallenge = $this->connection->prepare
        (
            "INSERT INTO challenge (clubid, date, time) VALUES ((SELECT clubid from club WHERE name = ?), ?, ?)"
        );
        $statementChallenge->bind_param("sss", $clubName, $mysqlDate, $time);
        if ($statementChallenge->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Upon creation of a challenge, returns the newly auto-generated challenge ID.
     * This is further used as a reference in the player_challenge table, as part
     * of a compound key. The limitation of this function is that it returns the
     * latest entry, which will run into concurrency issues if the app gets busy.
     */
    function get_challenge_id()
    {
        /* Prepare and execute statement */
        $statementGetID = $this->connection->prepare
        (
            "SELECT challengeid FROM challenge ORDER BY challengeid DESC LIMIT 1"
        );
        $statementGetID->bind_result($challengeID);
        $statementGetID->execute();
        $statementGetID->fetch();
        $statementGetID->close();
        $challengeID = strval($challengeID); // Returned in string format as it is fed back as a parameter
        return $challengeID;
    }

    /**
     * @param $challengeid String autogenerated challenge ID, originally returned from create_challenge.
     * Use the autogenerated challenge ID to create player_challenge entries for both players.
     */
    function create_player_challenge($challengeID, $playerID, $opponentID)
    {
        $challengeID = intval($challengeID);

        /* Prepare and execute the user entry, 1 indicates that they initiated the challenge from their client */
        $playerID = intval($playerID);
        $statementUser = $this->connection->prepare
        (
            "INSERT INTO player_challenge (challengeid, playerid, didinitiate) VALUES (?, ?, 1)"
        );
        $statementUser->bind_param("ii", $challengeID, $playerID);
        if (!$statementUser->execute()) {
            return false;
        }
        $statementUser->close();

        /* Prepare and execute the opponent entry, 0 indicates that they are recieving the challenge */
        $opponentID = intval($opponentID);
        $statementOpponent = $this->connection->prepare
        (
            "INSERT INTO player_challenge (challengeid, playerid, didinitiate) VALUES (?, ?, 0)"
        );
        $statementOpponent->bind_param("ii", $challengeID, $opponentID);
        if (!$statementOpponent->execute()) {
            return false;
        }
        $statementOpponent->close();
        return true;    // Both statements executed successfully.

    }

    /**
     * Updated get_challenges method. First obtains a list of challenge ID's and initiation status'
     * from the player_challenge table, and inserts these into an array of keypair values. Using the
     * challenge ID's, query for challenge metadata as well as opponent data. As we iterate, append
     * the data from the initial array (used to identify challenge ID's) to the array for return.
     * This results in final array being correctly formatted for JSON encoding.
     */
    function get_challenges($playerID)
    {
        /* Construct a list of challenge ID's an initiation status'.
        -1 indicates that a result has not been posted and the match
        not yet played. */
        $statementGetIDList = $this->connection->prepare
        (
            "SELECT challengeid, didinitiate FROM player_challenge WHERE playerid = ? AND didwin = -1"
        );

        /* Prepare and execute statement */
        $playerID = intval($playerID);
        $statementGetIDList->bind_param("i", $playerID);
        $statementGetIDList->execute();
        $statementGetIDList->bind_result($challengeID, $didInitiate);
        $challengeList = array();
        while ($statementGetIDList->fetch()) {
            $locatedChallenge = array();
            $locatedChallenge["challengeid"] = $challengeID;
            $locatedChallenge["didinitiate"] = $didInitiate;
            array_push($challengeList, $locatedChallenge);
        }
        $statementGetIDList->close();

        /* For each identified challenge, fetch relevant data */
        $challenges = array();  // The final challenges array to be returned.
        foreach ($challengeList as $c) {
            /* Statement to fetch opponent data, != playerID refers to the opponent. */
            $statementGetOpponent = $this->connection->prepare
            (
                "SELECT playerid, fname, lname, elo, winstreak, hotstreak, matchesplayed, wins, losses, highestelo, clubchamp 
                        FROM player WHERE playerid = (SELECT playerid FROM player_challenge WHERE challengeid = ? AND playerid != ?)"
            );

            /* Statement to fetch challenge metadata */
            $statementGetChallengeData = $this->connection->prepare
            (
                "SELECT date, time, (SELECT name FROM club 
                        WHERE challenge.clubid = club.clubid ), accepted FROM challenge WHERE challengeid = ?"
            );

            $challenge = array();   // Array to hold a single challenge.

            /* Prepare and execute the opponent data statement */
            $statementGetOpponent->bind_param("ii", $c["challengeid"], $playerID);
            $statementGetOpponent->execute();
            $statementGetOpponent->bind_result($playerID, $fname, $lname, $elo, $winStreak, $hotStreak, $matchesPlayed, $numWins, $numLosses, $highestElo, $clubChamp);
            $statementGetOpponent->fetch();
            $statementGetOpponent->close();

            /* Prepare and execute the challenge data statement */
            $statementGetChallengeData->bind_param("i", $c["challengeid"]);
            $statementGetChallengeData->execute();
            $statementGetChallengeData->bind_result($date, $time, $location, $accepted);
            $statementGetChallengeData->fetch();
            $statementGetChallengeData->close();

            /* Append fetched data to the challenges array */
            $challenge["challengeid"] = $c["challengeid"];
            $challenge["didinitiate"] = $c["didinitiate"];
            $challenge["opponentid"] = $playerID;
            $challenge["fname"] = $fname;
            $challenge["lname"] = $lname;
            $challenge["elo"] = $elo;
            $challenge["winstreak"] = $winStreak;
            $challenge["hotstreak"] = $hotStreak;
            $challenge["matchesplayed"] = $matchesPlayed;
            $challenge["wins"] = $numWins;
            $challenge["losses"] = $numLosses;
            $challenge["highestelo"] = $highestElo;
            $challenge["clubchamp"] = $clubChamp;
            $challenge["date"] = $date;
            $challenge["time"] = $time;
            $challenge["location"] = $location;
            $challenge["accepted"] = $accepted;
            array_push($challenges, $challenge);
        }
        return $challenges;
    }

    /**
     * Operates similarly to get_challenges. This function simply returns data
     * related to completed matches, where a "match" is a challenge that has
     * been accepted and had its score reported.
     */
    function get_match_history($playerID)
    {
        /* Construct a list of challenge ID's an initiation status'.
        -1 indicates that a result has not been posted and the match
        not yet played. */
        $statementGetIDList = $this->connection->prepare
        (
            "SELECT challengeid, didwin FROM player_challenge WHERE playerid = ? AND didwin != -1"
        );

        /* Prepare and execute statement */
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

        /* For each identified match, fetch relevant data */
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

            $match = array();   // Array to hold a single match

            /* Prepare and execute the opponent data statement */
            $statementGetOpponent->bind_param("ii", $m["challengeid"], $playerID);
            $statementGetOpponent->execute();
            $statementGetOpponent->bind_result($opponentID, $fname, $lname);
            $statementGetOpponent->fetch();
            $statementGetOpponent->close();

            /* Prepare and execute the match data statement */
            $statementGetChallengeData->bind_param("i", $m["challengeid"]);
            $statementGetChallengeData->execute();
            $statementGetChallengeData->bind_result($date, $score);
            $statementGetChallengeData->fetch();
            $statementGetChallengeData->close();

            /* Append fetched data to the matches array */
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
    function delete_player($playerID)
    {
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

    /**
     * Sets the accepted status for a given challenge ID to true.
     */
    function accept_challenge($challengeID)
    {
        $statementAcceptChallenge = $this->connection->prepare
        (
            "UPDATE challenge SET accepted = 1 WHERE challengeid = ?"
        );
        $statementAcceptChallenge->bind_param("i", $challengeID);
        if ($statementAcceptChallenge->execute()) {
            return true;
        }
        return false;
    }

    /**
     * Shares functionality for both cancelling and declining a challenge.
     * Deletes the challenge with the associated ID from both challenge
     * and player_challenge tables.
     */
    function cancel_challenge($challengeID)
    {
        $statementPlayerChallenge = $this->connection->prepare
        (
            "DELETE FROM player_challenge WHERE challengeid = ?"
        );
        $statementPlayerChallenge->bind_param("i", $challengeID);
        $statementChallenge = $this->connection->prepare
        (
            "DELETE FROM challenge WHERE challengeid = ?"
        );
        $statementChallenge->bind_param("i", $challengeID);
        if (!$statementPlayerChallenge->execute()) {
            return false;
        }
        if (!$statementChallenge->execute()) {
            return false;
        }
        return true;
    }

    /**
     * Updates the database with fields relating to a reported result.
     */
    function post_result($challengeID, $winnerID, $loserID, $score, $winnerElo, $loserElo, $newHighestElo, $hotStreak)
    {
        $challengeID = intval($challengeID);
        $winnerID = intval($winnerID);
        $loserID = intval($loserID);
        $winnerElo = intval($winnerElo);
        $loserElo = intval($loserElo);
        $newHighestElo = intval($newHighestElo);
        $hotStreak = intval($hotStreak);

        /* Identify the current highest rated player at the winners club
        and check if the user has overtaken them. If they have, unset the
        club champion status for the current holder. */
        $currentClubChampion = $this->get_club_max_elo($winnerID);
        if ($winnerElo >= $currentClubChampion) {
            $clubChampion = 1;
            $this->remove_champion_status($currentClubChampion);
        } else {
            $clubChampion = 0;
        }

        /* Update the score in the challenges table. */
        $statementUpdateScore = $this->connection->prepare
        (
            "UPDATE challenge SET score = ? WHERE challengeid = ?"
        );
        $statementUpdateScore->bind_param("si", $score, $challengeID);
        if (!$statementUpdateScore->execute()) {
            return false;
        }
        $statementUpdateScore->close();

        /* Update the winner in the player_challenges table. */
        $statementUpdateWinner = $this->connection->prepare
        (
            "UPDATE player_challenge SET didwin = 1 WHERE challengeid = ? AND playerid = ?"
        );
        $statementUpdateWinner->bind_param("ii", $challengeID, $winnerID);
        if (!$statementUpdateWinner->execute()) {
            return false;
        }
        $statementUpdateWinner->close();

        /* Update the loser in the player_challenges table. */
        $statementUpdateLoser = $this->connection->prepare
        (
            "UPDATE player_challenge SET didwin = 0 WHERE challengeid = ? AND playerid = ?"
        );
        $statementUpdateLoser->bind_param("ii", $challengeID, $loserID);
        if (!$statementUpdateLoser->execute()) {
            return false;
        }
        $statementUpdateLoser->close();

        /* Update the player data for the winner. */
        $statementWinnerPlayer = $this->connection->prepare
        (
            "UPDATE player SET elo = ?, winstreak = winstreak + 1, hotstreak = ?, 
                  matchesplayed = matchesplayed + 1, wins = wins + 1, highestelo = ?, clubchamp = ? WHERE playerid = ?"
        );
        $statementWinnerPlayer->bind_param("iiiii", $winnerElo, $hotStreak, $newHighestElo, $currentClubChampion, $winnerID);
        if (!$statementWinnerPlayer->execute()) {
            return false;
        }
        $statementWinnerPlayer->close();

        /* Update the player data for the loser. */
        $statementLoserPlayer = $this->connection->prepare
        (
            "UPDATE player SET elo = ?, winstreak = 0, hotstreak = 0, 
                  matchesplayed = matchesplayed + 1, losses = losses + 1 WHERE playerid = ?"
        );
        $statementLoserPlayer->bind_param("ii", $loserElo, $loserID);
        if (!$statementLoserPlayer->execute()) {
            return false;
        }
        $statementLoserPlayer->close();

        return true;
    }

    /**
     * Identify the current highest Elo at a given users club.
     */
    function get_club_max_elo($winnerID)
    {
        /* Fetch every players elo from the users club. */
        $statementGetElos = $this->connection->prepare
        (
            "SELECT playerid, elo FROM player WHERE clubid = 
                    (SELECT clubid FROM player WHERE playerid = ?)"
        );
        $statementGetElos->bind_param("i", $winnerID);
        $statementGetElos->execute();
        $statementGetElos->bind_result($playerID, $elo);
        $elos = array();
        /* Don't append the users elo. */
        while ($statementGetElos->fetch()) {
            if ($playerID != $winnerID) {
                array_push($elos, $elo);
            }
        }
        $statementGetElos->close();
        return max($elos);  // Return the highest value.
    }

    /**
     * Unset the club champion status of the current club champion.
     */
    function remove_champion_status($currentClubChampion)
    {
        $statementRemoveChampion = $this->connection->prepare
        (
            "UPDATE player SET clubchamp = 0 WHERE elo = ?"
        );
        $statementRemoveChampion->bind_param("i", $currentClubChampion);
        $statementRemoveChampion->execute();
        $statementRemoveChampion->close();
    }

    /**
     * @deprecated
     * The initial get_challenges function. Data formatting
     * was not suitable for JSON encoding.
     */
    /* function get_challenges($playerid)
       {
           $result = array();
           $playerid = intval($playerid);
           $statement = $this->connection->prepare("SELECT challengeid FROM player_challenge WHERE playerid = ?");
           $statement->bind_param("i", $playerid);
           $statement->execute();
           $statement->bind_result($challengeid);
           $challengeids = array();
           while ($statement->fetch()) {
               array_push($challengeids, $challengeid);
           }
           $statement->close();
           $result["challengeid"] = $challengeids;
           $opponent_ids = array();
           $opponent_fnames = array();
           $opponent_lnames = array();
           foreach ($challengeids as $c) {
               $statement = $this->connection->prepare("SELECT playerid FROM player_challenge WHERE challengeid = ? AND playerid != ?");
               $statement_getdetails = $this->connection->prepare("SELECT fname, lname FROM player WHERE playerid = ?");
               $statement->bind_param("ii", $c, $playerid);
               $statement->execute();
               $statement->bind_result($opponentid);
               $statement->fetch();
               $statement->close();
               $statement_getdetails->bind_param("i", $opponentid);
               $statement_getdetails->execute();
               $statement_getdetails->bind_result($opponent_fname, $opponent_lname);
               $statement_getdetails->fetch();
               $statement_getdetails->close();
               array_push($opponent_ids, $opponentid);
               array_push($opponent_fnames, $opponent_fname);
               array_push($opponent_lnames, $opponent_lname);
           }
           $result["opponentid"] = $opponent_ids;
           $result["opponent_fname"] = $opponent_fnames;
           $result["opponent_lname"] = $opponent_lnames;
           $dates = array();
           $times = array();
           foreach ($challengeids as $c) {
               $statement = $this->connection->prepare("SELECT date, time FROM challenge WHERE challengeid = ?");
               $statement->bind_param("i", $c);
               $statement->execute();
               $statement->bind_result($date, $time);
               $statement->fetch();
               $statement->close();
               array_push($dates, $date);
               array_push($times, $time);
           }
           $result["date"] = $dates;
           $result["time"] = $times;
           return $result;
       } */
}
