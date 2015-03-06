<?php
require __DIR__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

Twig_Autoloader::register ();

// Load properties from config.ini
$ini_array = parse_ini_file ( "config.ini" );

// JANUS API credentials
global $janusApiCred;
global $janusApiURL;
$janusApiCred = $ini_array ['janusApiCred'];
$janusApiURL = $ini_array ['janusApiURL'];

// Path for tests into the IDE. Empty string in production
global $testPath;
$testPath = $ini_array ['TestPath'];

// The JSON source file to output
global $JSONFile;
$JSONFile = $testPath . $ini_array ['JSONFile'];

/**
 * LOGGING properties
 */
date_default_timezone_set ( 'Europe/Amsterdam' );

// create a log channel
$logFileName = $ini_array ['logFileName'];
$dateFormat = $ini_array ['dateFormat'];
$outputLayout = $ini_array ['outputLayout'] . "\n";
$formatter = new LineFormatter ( $outputLayout, $dateFormat );

$streamHandler = new StreamHandler ( $testPath . $logFileName, Logger::INFO );
$streamHandler->setFormatter ( $formatter );

global $logger;
$logger = new Logger ( 'GetConnections' );
$logger->pushHandler ( $streamHandler );

/**
 * FUNCTIONS
 */

/**
 *
 * @return an array of the result or False if the download fails
 */
function getConnections() {
	global $logger;
	global $janusApiCred;
	global $janusApiURL;
	$result;
	$logger->info ( "Accessing ".$janusApiURL);
	$ch = curl_init ();
	curl_setopt ( $ch, CURLOPT_URL, $janusApiURL . ".json" );
	curl_setopt ( $ch, CURLOPT_USERPWD, $janusApiCred );
	curl_setopt ( $ch, CURLOPT_HEADER, 0 );
	curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
	
	$output = curl_exec ( $ch );
	$info = curl_getinfo ( $ch );
	if ($info ['http_code'] == 0) {
		$logger->critical ( "Download of the Connections has failed. Something's wrong with the network..." );
		curl_close ( $ch );
		return false;
	} elseif ($output == false || $info ['http_code'] != '200') {
		$logger->critical ( "Download of the Connections has failed on HTTP Code: " . $info ['http_code'] );
		curl_close ( $ch );
		return false;
	} else {
		$result = json_decode ( $output, true );
		$logger->info ( "Download of ". count ( $result ['connections'] )." SAML connections array succeeded.");
		curl_close ( $ch );
		return $result;
	} // else
} // getConnections

/**
 *
 * @param Array $connections:
 *        	an array of SAML entities
 * @return Array of production IdPs
 */
function getProductionIdPIDs($connections) {
	global $logger;
	$ConnectionsArray = $connections ['connections'];
	$productionIdPs = 0;
	$idpsArray;
	
	foreach ( $ConnectionsArray as $key => $value ) {
		
		// Remove all SPs and non production IdP
		if (($value ['type'] == "saml20-idp") && ($value ['state'] == "prodaccepted")) {
			$idpsArray [$productionIdPs] = $key;
			$logger->debug ( "The entity " . $value ['name'] . " is added to the result" );
			$productionIdPs ++;
		} // if
	} // foreach
	
	$logger->info ( $productionIdPs . " production IdPs were found and will be processed." );
	return $idpsArray;
} // getProductionIdPArray

/**
 *
 * @param Array $idpsArray
 *        	containing all production IdP indexes in the Resource Registry
 */
function getIdPsMetadataArray($idpsArray) {
	global $logger;
	global $janusApiCred;
	global $janusApiURL;
	global $testPath;
	global $JSONFile;
	
	$ch = curl_init ();
	curl_setopt ( $ch, CURLOPT_USERPWD, $janusApiCred );
	curl_setopt ( $ch, CURLOPT_HEADER, 0 );
	curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
	$index = 0;
	$resultArray [] = "connections";
	
	foreach ( $idpsArray as $key => $value ) {
		curl_setopt ( $ch, CURLOPT_URL, $janusApiURL . "/" . $value . ".json" );
		$curlResult = curl_exec ( $ch );
		
		if ($curlResult == false) {
			$info = curl_getinfo ( $ch );
			$logger->error ( "Download of the IdP's connection number " . $value . " has failed on HTTP Code: " . $info ['http_code'] );
			$logger->error ( $info );
			die ( "Download of the IdP's connection number " . $key . " has failed on HTTP Code: " . $info ['http_code'] );
		} else {
			$entityMetadataArray = json_decode ( $curlResult, true );
			$resultArray [$index] = $entityMetadataArray;
			$index ++;
		} // else
	} // foreach
	curl_close ( $ch );
	
	// Print into a JSON file
	$fp = fopen ( $testPath ."output/".$JSONFile, 'w' );
	if ($fp == false) {
		$logger->critical ( "Cannot create output file: ". $testPath . "output/".$JSONFile. ". Check writing rights. Aborting..." );
		die ("Cannot create output file. Check writing rights. Aborting... \n");
	} // if
	
	fwrite ( $fp, json_encode ( $resultArray, JSON_PRETTY_PRINT ) );
	fclose ( $fp );
} // constructIdPArray

/**
 * ********************** MAIN **********************
 */
$processingTime = time();
$logger->info ( "*************START CURL Connections****************" );
$idpsArray [] = false;
$connections = getConnections ( $janusApiURL, $janusApiCred );
if ($connections != false) {
	$idpsArray = getProductionIdPIDs ( $connections );
	getIdPsMetadataArray ( $idpsArray );
} // if

$processingTime = time() - $processingTime;
$logger->info ( "\nRunning time: ". $processingTime. " seconds");
$logger->info ( "*************END CURL Connections****************" );


