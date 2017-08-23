<?php
include_once('../conf/config.inc.php');

// Constants
// TG Constants
define('BOT_TOKEN', $config['TG_TOKEN']);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
//ETMDB Constants
define("ETMDB_API_ACCESS_TOKEN", "Bearer " . $config['ETMDB_TOKEN']);

// Some more constants
define("ETMDB_SEARCH_MOVIES", 0);
define("ETMDB_SEARCH_PEOPLE", 1);
define("ETMDB_SEARCH_CINEMA", 2);
define("ETMDB_SEARCH_FILM_COMPANY", 3);

function getAccessToken() {
	
	if(checkIfAccessTokenIsStillVaild()){
		return getAccessTokenFromFile();
	} else {
		//Access token has expired we have to create a new one
		if(getAccessTokenFromFile() == "" && getRefreshTokenFromFile() == ""){
			//We do not have previously generated access token and refresh token we have to create a new one
		
			$accCurl = curl_init();
			curl_setopt($accCurl, CURLOPT_URL, "https://".$config['CLIENT_ID'].":".$config['CLIENT_SECRET']."@etmdb.com/api/oauth/token/");
			curl_setopt($accCurl, CURLOPT_POST, true);
			curl_setopt($accCurl, CURLOPT_POSTFIELDS, array(
				"grant_type" => "password",
				"username" => $config['USERNAME'],
				"password" => $config['PASSWORD'],
				"scope" => "write groups read"
			));
			curl_setopt($accCurl, CURLOPT_RETURNTRANSFER, true);
			$returnedJsonForNewToken = curl_exec($accCurl);
			$DecodedJSONForNewToken = json_decode($returnedJsonForNewToken, true);
			curl_close($accCurl);

			writeAccessTokenToFile($DecodedJSONForNewToken['access_token'] , $DecodedJSONForNewToken['refresh_token']);
			
			return $DecodedJSONForNewToken['access_token'];
		}else{
			//We have previously generated access token and refresh token... We can use the refresh token to make new one		
			$accCurl = curl_init();
			curl_setopt($accCurl, CURLOPT_URL, "https://etmdb.com/api/oauth/token/");
			curl_setopt($accCurl, CURLOPT_POST, true);
			curl_setopt($accCurl, CURLOPT_POSTFIELDS, array(
				"grant_type" => "refresh_token",
				"client_id" => $config['CLIENT_ID'],
				"client_secret" => $config['CLIENT_SECRET'],
				"refresh_token" => $getRefreshTokenFromFile()
			));
			curl_setopt($accCurl, CURLOPT_RETURNTRANSFER, true);
			$returnedJsonForRefreshing = curl_exec($accCurl);
			$DecodedJSONFromRefreshing = json_decode($returnedJsonForRefreshing, true);
			curl_close($accCurl);

			writeAccessTokenToFile($DecodedJSONFromRefreshing['access_token'] , $DecodedJSONFromRefreshing['refresh_token']);
		
			return $DecodedJSONFromRefreshing['access_token'];
		}
	}
}

function getAccessTokenFromFile() {
	
	$accessTokenStoreFile = fopen("accessT0k3n.tok" , "r");
	$accessT0k3n = fread($accessTokenStoreFile , 500000);
	
	list($accTok , $refTok) = explode(":" , $accessT0k3n);
	fclose($accessTokenStoreFile);
	return $accTok;
	
}

function getRefreshTokenFromFile() {
	
	$accessTokenStoreFile = fopen("accessT0k3n.tok" , "r");
	$accessT0k3n = fread($accessTokenStoreFile , 500000);
	list($accTok , $refTok) = explode(":" , $accessT0k3n);
	fclose($accessTokenStoreFile);
	return $refTok;
	
}

function writeAccessTokenToFile($accessTokenToWrite, $refreshTokenToWrite) {
	$accessTokenStoreFile = fopen("accessT0k3n.tok" , "w");
	fwrite($accessTokenStoreFile , $accessTokenToWrite . ":" . $refreshTokenToWrite);
	fclose($accessTokenStoreFile);
}

function checkIfAccessTokenIsStillVaild() {
	
	$header = array(
		'Accept: application/json',
		'Authorization: Bearer ' . getAccessTokenFromFile()
	);
	$CheckerCurl = curl_init();
	curl_setopt($CheckerCurl, CURLOPT_URL, 'https://etmdb.com/api/v1/movie/detail/1');
	curl_setopt($CheckerCurl, CURLOPT_HTTPHEADER, $header);
	curl_setopt($CheckerCurl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($CheckerCurl, CURLOPT_HEADER, true);
curl_setopt($CheckerCurl, CURLOPT_NOBODY  , true); 

	$ReturnedByServer = curl_exec($CheckerCurl);
	$HTTPCode = curl_getinfo($CheckerCurl, CURLINFO_HTTP_CODE);
	
curl_close($CheckerCurl);
	
	if($HTTPCode == 200) {
		return true;
	} else{
		return false;
	}	
}

function Search($SearchTerm, $WhereToSearch)
{

	// A pretty self-explanatory switch statement

	switch ($WhereToSearch) {
	case ETMDB_SEARCH_MOVIES:
		$MoviesSearchURL = "https://etmdb.com/api/v1/movie/search/$SearchTerm";
		$SearchURL = $MoviesSearchURL;
		break;

	case ETMDB_SEARCH_PEOPLE:
		$PeopleSearchURL = "https://etmdb.com/api/v1/people/search/$SearchTerm";
		$SearchURL = $PeopleSearchURL;
		break;

	case ETMDB_SEARCH_CINEMA:
		$CinemaSearchURL = "https://etmdb.com/api/v1/cinema/search/$SearchTerm";
		$SearchURL = $CinemaSearchURL;
		break;

	case ETMDB_SEARCH_FILM_COMPANY:
		$FilmCompanySearchURL = "https://etmdb.com/api/v1/company/search/$SearchTerm";
		$SearchURL = $FilmCompanySearchURL;
		break;

	default:
		$DefaultSearchURL = "https://etmdb.com/api/v1/movie/search/$SearchTerm";
		$SearchURL = $DefaultSearchURL;
	}

	$header = array(
		'Accept: application/json',
		'Authorization: Bearer '.getAccessToken()
	);
	
	$SearchCurl = curl_init();
	curl_setopt($SearchCurl, CURLOPT_URL, $SearchURL);
	curl_setopt($SearchCurl, CURLOPT_HTTPHEADER, $header);
	curl_setopt($SearchCurl, CURLOPT_RETURNTRANSFER, true);
	$FoundSearchResultsJSON = curl_exec($SearchCurl);

	// Don't forget to tidy up
	curl_close($SearchCurl);

	// Result is a JSON file
	return $FoundSearchResultsJSON;
}

function SendTextMessage($ChatID, $TextMessageToSend, $ParseMode, $ReplyMarkup)
{

	// Dont forget to URL encode the Message
	$TextMessageToSend = urlencode($TextMessageToSend);

	// Prepare URL
	$TGSendMessageURL = "";
	if ($ReplyMarkup != null) {
		$TGSendMessageURL = API_URL . "SendMessage?chat_id=" . $ChatID . "&text=" . $TextMessageToSend . "&parse_mode=" . $ParseMode . "&reply_markup=" . $ReplyMarkup;
	}
	else {
		$TGSendMessageURL = API_URL . "SendMessage?chat_id=" . $ChatID . "&parse_mode=" . $ParseMode . "&text=" . $TextMessageToSend;
	}

	$TGMessageReceivedResponse = file_get_contents($TGSendMessageURL);
	return $TGMessageReceivedResponse;
}

function ShowInlineResults($queryID, $ResultsToShow)
{
	$POSTField = array(
		"inline_query_id" => $queryID,
		"results" => json_encode($ResultsToShow)
	);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, API_URL . "answerInlineQuery");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $POSTField);
	$chResult = curl_exec($ch);
	curl_close($ch);
	return $chResult;
}

function JSONToInlineResultForMovies($JSONFromETMDB)
{
	$InlineResults = array();
	$DecodedJSONFromETMDB = json_decode($JSONFromETMDB, true);
	foreach($DecodedJSONFromETMDB as $SingleResult) {
		$MovieTitle = $SingleResult['movie_title'];
		$MoviePlot = $SingleResult['plot'];
		$MovieLength = $SingleResult['duration_minutes'];
		$MoviePoster = $SingleResult['poster_image'];
		$MovieYear = explode("-" , $SingleResult['release_date'])[0];
		$SomeVoodoo = "\r\n\r\n <a href='{$MoviePoster}' >-</a> <a href='https://etmdb.com' >ETMDB</a>";
		$MovieSummaryInHTMLFormat = "<b>{$MovieTitle} ({$MovieYear})</b>\r\n️🍿 <i>Duration: {$MovieLength} min</i>\r\n\r\n" . strip_tags($MoviePlot) . $SomeVoodoo;
		$SingleResultPhotoURL = $MoviePoster;
		$SingleResultThumbnailURL = $MoviePoster;
		$SingleResultTitle = $MovieTitle;
		$SingleResultDescription = strip_tags($MoviePlot);
		$InlineEntry = array(
			"type" => "article",
			"id" => "" . rand() ,
			"thumb_url" => $SingleResultThumbnailURL,
			"title" => $SingleResultTitle,
			"description" => $SingleResultDescription,
			"input_message_content" => array(
				"message_text" => $MovieSummaryInHTMLFormat,
				"parse_mode" => "HTML"
			)
		);
		array_push($InlineResults, $InlineEntry);
		$Temp = json_encode($InlineEntry);
	}

	return $InlineResults;
}

function JSONToInlineResultForPeople($JSONFromETMDB)
{
	$InlineResults = array();
	$DecodedJSONFromETMDB = json_decode($JSONFromETMDB, true);
	foreach($DecodedJSONFromETMDB as $SingleResult) {
		$PersonFirstName = $SingleResult['user']['first_name'];
		$PersonLastName = $SingleResult['user']['last_name'];
		$PersonEmail = $SingleResult['user']['email'];
		$PersonGender = $SingleResult['gender_MF'];
		$PersonBirthDate = $SingleResult['date_of_birth'];
		$PersonBirthPlace = $SingleResult['birth_place'];
		$PersonHeight = $SingleResult['height'];
		$PersonSpouse = $SingleResult['spouse'];
		$PersonNickName = $SingleResult['nickname'];
		
		//Capitalize first letter
		$PersonGender[0]=strtoupper($PersonGender[0]);
		
		if($PersonHeight==""){$PersonHeight= 'UNKNOWN';}
		if($PersonBirthDate==""){$PersonBirthDate= 'UNKNOWN';}
		if($PersonBirthPlace==""){$PersonBirthPlace= 'UNKNOWN';}
		if($PersonEmail==""){$PersonEmail= 'UNKNOWN';}
		
		
		
		
		
		$PersonSummaryInHTMLFormat = "🎭 \r\n<b>{$PersonFirstName}  {$PersonLastName}</b> \r\nGender: $PersonGender \r\nHeight: {$PersonHeight}\r\nDate Of Birth: {$PersonBirthDate}\r\nBirth Place: {$PersonBirthPlace} \r\n\r\nEmail: ({$PersonEmail})\r\n\r\n" . strip_tags($PersonName);
		$SingleResultTitle = $PersonFirstName . " " . $PersonLastName;
		$SingleResultDescription = strip_tags($PersonGender);
		$InlineEntry = array(
			"type" => "article",
			"id" => "" . rand() ,
			"title" => $SingleResultTitle,
			"description" => $SingleResultDescription,
			"input_message_content" => array(
				"message_text" => $PersonSummaryInHTMLFormat,
				"parse_mode" => "HTML"
			)
		);
		array_push($InlineResults, $InlineEntry);
		$Temp = json_encode($InlineEntry);
	}

	return $InlineResults;
}

function JSONToInlineResultForCinemas($JSONFromETMDB)
{
	$InlineResults = array();
	$DecodedJSONFromETMDB = json_decode($JSONFromETMDB, true);
	foreach($DecodedJSONFromETMDB as $SingleResult) {
		$CinemaName = $SingleResult['cinema_name'];
		$CinemaEstablishedDate = $SingleResult['established_in'];
		$CinemaDescription = $SingleResult['description'];
		$CinemaOpenTime = $SingleResult['opens_at'];
		$CinemaCloseTime = $SingleResult['closes_at'];
		$CinemaPoster = $SingleResult['cinema_poster_image'];
		$SomeVoodoo = "\r\n\r\n <a href='{$CinemaPoster}' >-</a> <a href='https://etmdb.com' >ETMDB</a>";
		$CinemaSummaryInHTMLFormat = "<b>{$CinemaName}</b> \r\n📽️ <i>Open From: {$CinemaOpenTime}-{$CinemaCloseTime}</i>\r\n\r\n" . strip_tags($CinemaDescription) ."\r\n\r\n Established In: {$CinemaEstablishedDate}". $SomeVoodoo;
		$SingleResultTitle = $CinemaName;
		$SingleResultDescription = strip_tags("Open From: {$CinemaOpenTime} - {$CinemaCloseTime}");
		$SingleResultThumbnailURL = $CinemaPoster;
		$InlineEntry = array(
			"type" => "article",
			"id" => "" . rand() ,
			"title" => $SingleResultTitle,
			"thumb_url" => $SingleResultThumbnailURL,
			"description" => $SingleResultDescription,
			"input_message_content" => array(
				"message_text" => $CinemaSummaryInHTMLFormat,
				"parse_mode" => "HTML"
			)
		);
		array_push($InlineResults, $InlineEntry);
		$Temp = json_encode($InlineEntry);
	}

	return $InlineResults;
}

function JSONToInlineResultForCompanies($JSONFromETMDB)
{
	$InlineResults = array();
	$DecodedJSONFromETMDB = json_decode($JSONFromETMDB, true);
	foreach($DecodedJSONFromETMDB as $SingleResult) {
		$CompanyName = $SingleResult['company_name'];
		$CompanyEstablishedDate = $SingleResult['established_in'];
		$CompanyDescription = $SingleResult['description'];
		$CompanyOpenTime = $SingleResult['opens_at'];
		$CompanyCloseTime = $SingleResult['closes_at'];
		$CompanyPoster = $SingleResult['company_poster_image'];
		$SomeVoodoo = "\r\n\r\n <a href='{$CompanyPoster}' >-</a> <a href='https://etmdb.com' >ETMDB</a>";
		$CompanySummaryInHTMLFormat = "<b>{$CompanyName}</b>\r\n🎬 <i>Open From: {$CompanyOpenTime}-{$CompanyCloseTime}</i>\r\n\r\n" . strip_tags($CompanyDescription) ."\r\n\r\nEstablished In: {$CompanyEstablishedDate}". $SomeVoodoo;
		$SingleResultTitle = $CompanyName;
		$SingleResultDescription = strip_tags("Open From: {$CompanyOpenTime} - {$CompanyCloseTime}");
		$SingleResultThumbnailURL = $CompanyPoster;
		$InlineEntry = array(
			"type" => "article",
			"id" => "" . rand() ,
			"title" => $SingleResultTitle,
			"thumb_url" => $SingleResultThumbnailURL,
			"description" => $SingleResultDescription,
			"input_message_content" => array(
				"message_text" => $CompanySummaryInHTMLFormat,
				"parse_mode" => "HTML"
			)
		);
		array_push($InlineResults, $InlineEntry);
		$Temp = json_encode($InlineEntry);
	}

	return $InlineResults;
}

// check if anything is received

$JSONFromTG = file_get_contents("php://input");

if (isset($JSONFromTG)) {

	// decode JSON to array

	$DecodedJSONFromTG = json_decode($JSONFromTG, true);
	$SessionChatID = $DecodedJSONFromTG["message"]["chat"]["id"];
	$SessionQueryID = $DecodedJSONFromTG["inline_query"]["id"];

	// if received a PM

	if (isset($SessionChatID)) {
		$MessageText = $DecodedJSONFromTG['message']['text'];

		// This if statement works on all /commands sent to the bot 
		// This is made to prevent user confusion
		if ($MessageText[0] == "/" ) {
			$Keyboard = array(
				"inline_keyboard" => array(
					array(
						array(
							"text" => "Search Movies",
							"switch_inline_query_current_chat" => ""
						)
					) ,
					array(
						array(
							"text" => "Search People (/a)",
							"switch_inline_query_current_chat" => "/a "
						)
					) ,
					array(
						array(
							"text" => "Search Cinemas (/b)",
							"switch_inline_query_current_chat" => "/b "
						)
					) ,
					array(
						array(
							"text" => "Search Film Companies (/c)",
							"switch_inline_query_current_chat" => "/c "
						)
					)
				)
			);
			$Keyboard = json_encode($Keyboard, true);
			SendTextMessage($SessionChatID, "What would you like to search for?", "HTML", $Keyboard);
		}else if(isset($DecodedJSONFromTG['message']['entities'])){
				//This works on messages that contain entities, such as the article the bot receives when an inline result is chosen
		}
		else {
			$Keyboard = array(
				"inline_keyboard" => array(
					array(
						array(
							"text" => "Search Movies",
							"switch_inline_query_current_chat" => $MessageText
						)
					) ,
					array(
						array(
							"text" => "Search People (/a)",
							"switch_inline_query_current_chat" => "/a " . $MessageText
						)
					) ,
					array(
						array(
							"text" => "Search Cinemas (/b)",
							"switch_inline_query_current_chat" => "/b " . $MessageText
						)
					) ,
					array(
						array(
							"text" => "Search Film Companies (/c)",
							"switch_inline_query_current_chat" => "/c " . $MessageText
						)
					)
				)
			);
			$Keyboard = json_encode($Keyboard, true);
			SendTextMessage($SessionChatID, "<i>Where should I search for: </i><b>" . $MessageText . "</b>", "HTML", $Keyboard);
		}
	}

	// if received an inline query

	else
	if (isset($SessionQueryID)) {
		$queryText = $DecodedJSONFromTG["inline_query"]["query"];
		$WhatToSearchFor = $queryText;
		$InlineResults = array();

		// Split command and query

		if ($queryText[0] == '/' && $queryText[1] != ' ') {
			$WhatToSearchFor = substr($queryText, 3);
			if ($queryText[1] == 'a') {

				// search in people

				$JSONFromETMDB = Search($WhatToSearchFor, ETMDB_SEARCH_PEOPLE);
				$InlineResults = JSONToInlineResultForPeople($JSONFromETMDB);
			}
			else
			if ($queryText[1] == 'b') {

				// search in cinemas

				$JSONFromETMDB = Search($WhatToSearchFor, ETMDB_SEARCH_CINEMA);
				$InlineResults = JSONToInlineResultForCinemas($JSONFromETMDB);
			}
			else
			if ($queryText[1] == 'c') {

				// search film companies

				$JSONFromETMDB = Search($WhatToSearchFor, ETMDB_SEARCH_FILM_COMPANY);
				$InlineResults = JSONToInlineResultForCompanies($JSONFromETMDB);
			}
		}
		else {
			$JSONFromETMDB = Search($WhatToSearchFor, ETMDB_SEARCH_MOVIES);
			$InlineResults = JSONToInlineResultForMovies($JSONFromETMDB);
		}

		ShowInlineResults($SessionQueryID, $InlineResults);
	}
}

?>
