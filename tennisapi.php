<?php

/**
 * Handles HTTP encoded requests to interact with the tennis_ladder database.
 *
 * This API performs the following high level tasks:
 * - Valid parameter checks
 * - Login requests
 * - User creation requests
 * - Data fetching and formatting for tennis ladder and challenge display
 * - Challenge creation and modification
 * - Achievement requirement checking
 *
 * The implemented API architecture is discussed in the following article:
 * https://www.simplifiedcoding.net/android-mysql-tutorial-to-perform-basic-crud-operation/#Android-MySQL-Tutorial
 */
require_once dirname(__FILE__) . "/PlayerRequest.php";
require_once dirname(__FILE__) . "/ChallengeRequest.php";
require_once dirname(__FILE__) . "/LadderRequest.php";

/**
 * @param $params
 * Check for necessary parameters.
 */
function checkForParameters($params) {
    $complete = true;
    /* Check for each parameter. */
    foreach ($params as $p) {
        if (!isset($_POST[$p]) || mb_strlen($_POST[$p]) == 0) {
            $complete = false;
        }
    }
    /* Log error and return. */
    if (!$complete) {
        $response = array();
        $response["error"] = true;
        $response['message'] = 'Please enter all necessary fields';
        echo json_encode($response);
        die();
    }
}

/* Hold the response for a request. */
$response = array();

/**
 * Determine the nature of API request. If the request is unrecognised, return an error message.
 */
if (isset($_GET["tennisapi"]))
{
    switch($_GET["tennisapi"]) {

        /* Login request, if successful return player related data. */
        case "login":
            checkForParameters(array("email", "password"));   // Check for parameters.
            $tennisDB = new PlayerRequest();
            $success = $tennisDB->login($_POST["email"], $_POST["password"]);
            if ($success) {
                $response["error"] = false;
                $response["message"] = "Login successful";
                $response["player"] = $tennisDB->getPlayerData($_POST["email"]);    // Fetch associated player data.
                $response["achievements"] = $tennisDB->getAchievementList();    // Fetch player achievements.
            }
            else {
                $response["error"] = true;
                $response["message"] = "Invalid username or password";
            }
            break;

            /* Signup request. */
        case "create_player":
            checkForParameters(array("email", "password", "contactno", "fname", "lname", "clubname"));    // Check for parameters.
            $tennisDB = new PlayerRequest();
            $errorCode = $tennisDB->createPlayer(
                $_POST["email"],
                $_POST["password"],
                $_POST["contactno"],
                $_POST["fname"],
                $_POST["lname"],
                $_POST["clubname"]
            );
            if ($errorCode == 0) {
                $response["error"] = false;
                $response["message"] = "Player created";
            }
            else if ($errorCode == 1062) {
                $response["error"] = true;
                $response["message"] = "Email is already in use";
            }
            break;

            /* Obtain a list of valid clubs to populate menu selection. */
        case "get_clubs":
            $tennisDB = new PlayerRequest();
            $response["clubs"] = $tennisDB->getClubs();
            $response["error"] = false;
            $response["message"] = "Club data retrieved";
            break;

            /* Use a player ID to fetch player data upon a successful login. */
        case "get_player_data":
            if (isset($_GET["playerid"])) {
                $tennisDB = new PlayerRequest();
                $response["player"] = $tennisDB->getPlayerData($_GET["playerid"]);
                $response["error"] = false;
                $response["message"] = "Player data retrieved";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Please provide a player ID";
            }
            break;

            /* Fetch the data required to populate the ladder fragment. */
        case "get_ladder_profile_data":
            $tennisDB = new LadderRequest();
            $response["players"] = $tennisDB->getLadderProfileData();
            $response["error"] = false;
            $response["message"] = "Ladder data retrieved";
            break;

            /* Fetches active challenges for a given player ID. */
        case "get_challenges":
            if (isset($_GET["playerid"])) {
                $tennisDB = new ChallengeRequest();
                $response["challenges"] = $tennisDB->getChallenges($_GET["playerid"]);
                $response["error"] = false;
                $response["message"] = "Challenges retrieved";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Please provide a player ID";
            }
            break;

        /* Fetches the match history for a given player ID. */
        case "get_match_history":
            if (isset($_GET["playerid"])) {
                $tennisDB = new PlayerRequest();
                $response["challenges"] = $tennisDB->getMatchHistory($_GET["playerid"]);
                $response["error"] = false;
                $response["message"] = "Match history retrieved";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Please provide a player ID";
            }
            break;

            /* Creates an entry to the challenge table containing challenge metadata. If this
            is successful, return the autogenerated challenge ID. */
        case "create_challenge":
            checkForParameters(array("clubname", "date", "time"));    // Check for parameters.
            $tennisDB = new ChallengeRequest();
            $success = $tennisDB->createChallenge($_POST["clubname"], $_POST["date"], $_POST["time"]);
            if ($success) {
                $response["challengeid"] = $tennisDB->getChallengeID(); // Return the newly created challenge ID.
                $response["error"] = false;
                $response["message"] = "Challenge created";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Error creating challenge";
            }
            break;

            /* Creates a player_challenge database entry for both players using the autogenerated
            challenge ID from the create_challenge API call. */
        case "create_player_challenge":
            checkForParameters(array("challengeid", "playerid", "opponentid"));   // Check for parameters.
            $tennisDB = new ChallengeRequest();
            $success = $tennisDB->createPlayerChallenge($_POST["challengeid"], $_POST["playerid"], $_POST["opponentid"]);
            if ($success) {
                $response["error"] = false;
                $response["message"] = "Challenge created";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Error creating challenge";
            }
            break;

            /* Accept a challenge with a given ID. */
        case "accept_challenge":
            if (isset($_GET["challengeid"])) {
                $tennisDB = new ChallengeRequest();
                $tennisDB->acceptChallenge($_GET["challengeid"]);
                $response["error"] = false;
                $response["message"] = "Challenge accepted";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Please provide a challenge ID";
            }
            break;

            /* Cancel(shares functionality with decline) a challenge with a given ID. */
        case "cancel_challenge":
            if (isset($_GET["challengeid"])) {
                $tennisDB = new ChallengeRequest();
                $tennisDB->cancelChallenge($_GET["challengeid"]);
                $response["error"] = false;
            }
            else {
                $response["error"] = true;
                $response["message"] = "Please provide a challenge ID";
            }
            break;

            /* Posts a result for given challenge ID. This also includes updating player data
            including Elo and win/loss for achievement checking. */
        case "post_result":
            checkForParameters(array("challengeid", "winnerid", "loserid", "score", "winnerelo", "loserelo", "newhighestelo", "hotstreak"));
            $tennisDB = new ChallengeRequest();
            $success = $tennisDB->postResult
            (
                $_POST["challengeid"],
                $_POST["winnerid"],
                $_POST["loserid"],
                $_POST["score"],
                $_POST["winnerelo"],
                $_POST["loserelo"],
                $_POST["newhighestelo"],
                $_POST["hotstreak"]
            );
            if ($success) {
                $response["error"] = false;
                $response["message"] = "Result submitted";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Error submitting result";
            }
            break;

            /* Post a user-obtained achievement. */
        case "post_achievement":
            checkForParameters(array("achievementid", "playerid"));
            $tennisDB = new PlayerRequest();
            $success = $tennisDB->postAchievement($_POST["achievementid"], $_POST["playerid"]);
            if ($success) {
                $response["error"] = false;
                $response["message"] = "Achievement posted";
            }
            else {
                $response["error"] = true;
                $response["message"] = "Error posting achievement";
            }
            break;

        /* Delete a player with a given ID. */
        case "delete_player":
            if (isset($_GET["playerid"])) {
                $tennisDB = new PlayerRequest();
                $tennisDB->deletePlayer($_GET["playerid"]);
                $response["error"] = false;
                $response["message"] = "Player deleted";
            } else {
                $response["error"] = true;
                $response["message"] = "Please provide a player ID";
            }
            break;

        /* If no matching API call is found, return an error. */
        default:
            $response["error"] = true;
            $response["message"] = "API functionality does not exist";
    }
}
/* If the API name is invalid, return error status. */
else {
    $response["error"] = true;
    $response["message"] = "Invalid API call";
}
echo json_encode($response);    // Return the JSON encoded response.