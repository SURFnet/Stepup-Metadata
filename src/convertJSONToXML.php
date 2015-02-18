<?php
include ('log4php/Logger.php');
//require_once 'vendor/twig/twig/lib/Twig/Autoloader.php';
require __DIR__.'/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// Logger::configure ( 'config.xml' );

Twig_Autoloader::register ();

// create a log channel
$dateFormat = "Y-m-d H:i:s";
$output = "[%datetime%] [%channel%] [%level_name%] %message% %context% %extra%\n";
$formatter = new LineFormatter($output, $dateFormat);

$streamHandler = new StreamHandler('../log/convertJSONToXML.log', Logger::DEBUG);
$streamHandler->setFormatter($formatter);

$logger = new Logger('defaultLogger');
$logger->pushHandler($streamHandler);

// add records to the log
// $log->addWarning('Foo');
// $log->addError('Bar');


/**
 * PARAMS to configure *
 */

// $logger = Logger::getLogger ( 'myLogger' );

// The JSON source file to parse
$JSONFile = "../input/entities.json";

// The SUUAS SSO URL
$StepUpIdPSSOEndpoint = "https://stepup.surfconext.nl/authentication/idp/single-sign-on/";

/**
 * FUNCTIONS *
 */

/**
 * Reads a JSON File and extract all the SURFconext IdP in production
 * return an array of these IdP
 */
function extractIdPFromJSON($JSONfileName) {
// 	$logger = Logger::getLogger ( 'myLogger' );
	$logger->info ( "Extracting IdPs information from JSON source file..." );
	
	$JSONentities = file_get_contents ( $JSONfileName );
	$connections = json_decode ( $JSONentities, true );
	$connectionSize = count ( $connections ['connections'] );
	$logger->info ( "The JSON file contains " . $connectionSize . " entries" );
	
	$entitiesArray = $connections ['connections'];
	
	foreach ( $entitiesArray as $key => $value ) {
		
		// Remove all SPs and non production IdP
		if (($value ['type'] == "saml20-sp") || ! $value ['isActive'] || ($value ['state'] !== "prodaccepted")) {
			unset ( $entitiesArray [$key] );
			$logger->debug ( "The entity " . $value ['name'] . " is removed from the result." );
		} // if
	} // foreach
	  
	// RESULT
	$logger->info ( "Number of entities left: " . count ( $entitiesArray ) );
	return $entitiesArray;
} // extractIdPFromJSON

/**
 * Adapt the IdP informations:
 * - Remove useless info for Metadata
 * - replace all SSO locations by Suuas own SSO location + IdP entityID hash
 * takes a valid IdPs array as argument
 * RETURN a "cleaned" IdP Array
 */
function cleanIdPInfos($IdPsArray) {
	$logger = Logger::getLogger ( 'myLogger' );
	
	$logger->info ( "Getting rid of useless IdPs informations" );
	
	foreach ( $IdPsArray as $key => $value ) {
		$logger->debug ( "The entity " . $value ['name'] . " is processed." );
		
		unset ( $IdPsArray [$key] ['allowedConnections'] );
		unset ( $IdPsArray [$key] ['blockedConnections'] );
		unset ( $IdPsArray [$key] ['disableConsentConnections'] );
		unset ( $IdPsArray [$key] ['updatedByUserName'] );
		unset ( $IdPsArray [$key] ['createdAtDate'] );
		unset ( $IdPsArray [$key] ['updatedAtDate'] );
		unset ( $IdPsArray [$key] ['updatedFromIp'] );
		unset ( $IdPsArray [$key] ['updatedAtDate'] );
		unset ( $IdPsArray [$key] ['allowAllEntities'] );
		unset ( $IdPsArray [$key] ['parentRevisionNr'] );
		unset ( $IdPsArray [$key] ['revisionNote'] );
		unset ( $IdPsArray [$key] ['revisionNr'] );
		unset ( $IdPsArray [$key] ['type'] );
		unset ( $IdPsArray [$key] ['state'] );
		unset ( $IdPsArray [$key] ['isActive'] );
	} // foreach
	
	return $IdPsArray;
} // cleanIdPInfos

/**
 * replace all SSO locations by Suuas own SSO location
 * Add the md5 hash value at the tailing end of the SSO location URL
 * e.g.
 * Location="https://engine.surfconext.nl/authentication/idp/single-sign-on/key:default/80e917885da2dd2624b1408b6b69fa2a"
 */
function replaceIdPsSSOendpoints($IdPsArray, $StepUpIdPSSOEndpoint) {
	$logger = Logger::getLogger ( 'myLogger' );
	
	$logger->info ( "Preparing the IdPs SSO URL for transparent metadata..." );
	
	foreach ( $IdPsArray as $key => $value ) {
		
		$IdPEntityIDHash = md5 ( $value ['name'] );
		
		$logger->debug ( "Hash value is " . $IdPEntityIDHash );
		
		// We keep only HTTP-Redirect binding
		$SSOBindings = $value ['metadata'] ['SingleSignOnService'];
		
		foreach ( $SSOBindings as $key2 => $value2 ) {
			
			if ($value2 ['Binding'] != "urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect") {
				
				// remove useless bindings
				unset ( $IdPsArray [$key] ['metadata'] ['SingleSignOnService'] [$key2] );
			} else {
				// replace the HTTP redirect binding by Step up IdP SSO endpoint
				$value2 ['Location'] = $StepUpIdPSSOEndpoint . "key:default/" . $IdPEntityIDHash;
				$IdPsArray [$key] ['metadata'] ['SingleSignOnService'] ['Location'] = $StepUpIdPSSOEndpoint . "key:default/" . $IdPEntityIDHash;
			} // else
		} // foreach
	} // foreach
	
	$logger->info ( count ( $IdPsArray ) . " IdPs SSO endpoints were replaced." );
	return $IdPsArray;
} // replaceIdPsSSOendpoints

/**
 * INPUT an array containing SAML IdP informations
 * OUTPUT an EntityDescriptor XML file for each IdP processed
 * Uses TWIG templates engine with SURFconext IdP EntityDescriptor template
 */
function outputEntityDescriptors($IdPsArray) {
	$logger = Logger::getLogger ( 'myLogger' );
	$logger->info ( "Preparing EntityDescriptors files..." );
	
	// Init Twig
	$loader = new Twig_Loader_Filesystem ( '../templates/' );
	$twig = new Twig_Environment ( $loader, array (
			'cache' => '/tmp/',
			'debug' => true,
			'auto_reload' => true 
	) );
	
	// Buid a single entity XML file
	$file = 'entity-template-surfconext.twig';
	$counter = 0;
	// Output the result into an entityDescriptor XML file
	foreach ( $IdPsArray as $key => $value ) {
		
		// Prepare the output file name
		$newfile = "entity-" . md5 ( $value ['name'] ) . ".xml";
		$logger->debug ( $newfile );
		
		// Use that new file as a template
		$template = $twig->loadTemplate ( $file );
		// Populate the template
		$output = $template->render ( $value );
		$logger->info ( "Outputting " . $value ['name'] );
		
		// Move the outputs to the output folder
		file_put_contents ( '../output/' . $newfile, $output );
		$counter ++;
	} // foreach
	
	$logger->info ( $counter . " IdPs metadata XML files were created" );
} // outputEntityDescriptors

/**
 * ********************** MAIN **********************
 */

$logger->info ( "*************START****************" );

$IdPArray = extractIdPFromJSON ( $JSONFile );
$CleanIdPArray = cleanIdPInfos ( $IdPArray );
$JSONSuuasIdPMD = replaceIdPsSSOendpoints ( $CleanIdPArray, $StepUpIdPSSOEndpoint );
outputEntityDescriptors ( $JSONSuuasIdPMD );

$logger->info ( "**************END*****************" );

?>