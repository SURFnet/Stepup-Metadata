<?php
require __DIR__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

Twig_Autoloader::register ();


/**
 * PARAMS to configure *
 */
//Load properties from config.ini
$ini_array = parse_ini_file("config.ini");

// Path for tests into the IDE. Empty string in production
global $testPath;
$testPath = $ini_array['TestPath'];

// The JSON source file to parse
$JSONFile = $testPath.$ini_array['JSONFile'];

// The SUUAS SSO URL
$StepUpIdPSSOEndpoint = $ini_array['StepUpIdPSSOEndpoint'];


/**
 * LOGGING properties
 */

// create a log channel
$dateFormat = "Y-m-d H:i:s";
$output = "[%datetime%] [%channel%] [%level_name%] %message% \n";
$formatter = new LineFormatter ( $output, $dateFormat );
if (! file_exists ( $testPath . 'log/convertJSONToXML.log' )) {
	$fh = fopen ( $testPath . 'log/convertJSONToXML.log', 'w' ) or die ( "Cannot create log file. Check Write rights for the unix user running the program. \n" );
} // if

$streamHandler = new StreamHandler ( $testPath . 'log/convertJSONToXML.log', Logger::INFO );
$streamHandler->setFormatter ( $formatter );

global $logger;
$logger = new Logger ( 'defaultLogger' );
$logger->pushHandler ( $streamHandler );

global $processedIdPs;

/**
 * FUNCTIONS *
 */

/**
 * Reads a JSON File and extract all the SURFconext IdP in production
 * return an array of these IdP
 */
function extractIdPFromJSON($JSONfileName) {
	global $logger;
	global $processedIdPs;
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
	$processedIdPs = count ( $entitiesArray );
	$logger->info ( "Number of entities left: " . $processedIdPs );
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
	global $logger;
	
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
 * Location="SSO_BASE_URL+key:default/+EntityIDHash"
 */
function replaceIdPsSSOendpoints($IdPsArray, $StepUpIdPSSOEndpoint) {
	global $logger;
	
	$logger->info ( "Preparing the IdPs SSO URL for transparent metadata..." );
	
	foreach ( $IdPsArray as $key => $value ) {
		
		$IdPEntityIDHash = md5 ( $value ['name'] );
		
		$logger->debug ( "Hash value is " . $IdPEntityIDHash );
		
		// We keep only HTTP-Redirect binding
		$SSOBindings = $value ['metadata'] ['SingleSignOnService'];
		
		// Remove and replace all SSO binding by the unique Suaas SSO endpoint
		foreach ( $SSOBindings as $key2 => $value2 ) {
			
			unset ( $IdPsArray [$key] ['metadata'] ['SingleSignOnService'] [$key2] );
			// replace the HTTP redirect binding by Step up IdP SSO endpoint
			$value2 ['Location'] = $StepUpIdPSSOEndpoint . "key:default/" . $IdPEntityIDHash;
			
		} // foreach
		
		$IdPsArray [$key] ['metadata'] ['SingleSignOnService'] [0] ['Binding'] = "urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect";
		$IdPsArray [$key] ['metadata'] ['SingleSignOnService'] [0] ['Location'] = $StepUpIdPSSOEndpoint . "key:default/" . $IdPEntityIDHash;

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
	global $logger;
	global $processedIdPs;
	global $testPath;
	
	$logger->info ( "Preparing " . $processedIdPs . " entity descriptors files" );
	
	// Init Twig
	$loader = new Twig_Loader_Filesystem ( $testPath . 'templates/' );
	$twig = new Twig_Environment ( $loader, array (
			'cache' => '/tmp/',
			'debug' => true,
			'auto_reload' => true 
	) );
	$twig->addExtension ( new Twig_Extension_Debug () );
	
	// Buid a single entity descriptor XML file per IdP
	$entityDescritorTemplate = 'entityDescriptor.twig';
	$counter = 0;
	// Output the result into an entityDescriptor XML file
	foreach ( $IdPsArray as $key => $value ) {
		
		// Prepare the output file name
		$newfile = "entity-" . md5 ( $value ['name'] ) . ".xml";
		$logger->debug ( $newfile );
		
		// Use that new file as a template
		$template = $twig->loadTemplate ( $entityDescritorTemplate );
		// Populate the template
		$output = $template->render ( $value );
		$logger->info ( "Outputting " . $value ['name'] );
		
		// Move the outputs to the output folder
		file_put_contents ( $testPath . 'output/' . $newfile, $output );
		$counter ++;
	} // foreach
	  
	// Sanity Check
	if ($counter != $processedIdPs) {
		$logger->error ( "The program did not outputted the right amount of IdPs, only " . $counter . " have been processed instead of " . $processedIdP );
		die ( "The program did not outputted the right amount of IdPs, only " . $counter . " have been processed instead of " . $processedIdPs );
	}  // if
else
		$logger->info ( $counter . " IdPs metadata XML files were created for a total of " . $processedIdPs . " IdPs." );
} // outputEntityDescriptors

/**
 * INPUT an array containing SAML IdP informations
 * OUTPUT a unique EntitiesDescriptor XML file
 * Uses TWIG templates engine with SURFconext IdP EntitiesDescriptor template
 */
function outputEntitiesDescriptor($IdPsArray) {
	global $logger;
	global $testPath;
	$logger->info ( "Preparing an entities descriptor file" );
	
	// Init Twig
	$loader = new Twig_Loader_Filesystem ( $testPath . 'templates/' );
	$twig = new Twig_Environment ( $loader, array (
			'cache' => '/tmp/',
			'debug' => true,
			'auto_reload' => true 
	) );
	$twig->addExtension ( new Twig_Extension_Debug () );
	
	// Build an Entities descriptor XML file
	$entitiesDescritorTemplate = 'entitiesDescriptor.twig';
	$IdPsOutputFile = "suuas-transparent-metadata.xml";
	$template = $twig->loadTemplate ( $entitiesDescritorTemplate );
	// Populate the template
	$output = $template->render ( $IdPsArray );
	file_put_contents ( $testPath . 'output/' . $IdPsOutputFile, $output );
} // outputEntitiesDescriptor

/**
 * ********************** MAIN **********************
 */

$logger->info ( "*************START****************" );

$IdPArray = extractIdPFromJSON ( $JSONFile );
$CleanIdPArray = cleanIdPInfos ( $IdPArray );
$JSONSuuasIdPMD = replaceIdPsSSOendpoints ( $CleanIdPArray, $StepUpIdPSSOEndpoint );
outputEntitiesDescriptor ( $JSONSuuasIdPMD );
//print_r($JSONSuuasIdPMD);
//outputEntityDescriptors ( $JSONSuuasIdPMD );

$logger->info ( "**************END*****************" );

?>