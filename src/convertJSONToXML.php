<?php
require __DIR__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

Twig_Autoloader::register ();

// Load properties from config.ini
$ini_array = parse_ini_file ( "config.ini" );

// Path for tests into the IDE. Empty string in production
global $testPath;
$testPath = $ini_array ['TestPath'];

// The JSON source file to parse
global $JSONFile;
$JSONFile = $testPath . "output/".$ini_array ['JSONFile'];

// The SUUAS SSO URL
$StepUpIdPSSOEndpoint = $ini_array ['StepUpIdPSSOEndpoint'];

// Output Files
$outputFileName = $ini_array ['EntitiesDescriptorOutputFile'];
$outputEntityDesciptorsFiles = $ini_array ['outputEntityDescriptorFiles'];

/**
 * LOGGING properties
 */
date_default_timezone_set ( 'Europe/Amsterdam' );

$logFileName = $ini_array ['logFileName'];
$dateFormat = $ini_array ['dateFormat'];
$outputLayout = $ini_array ['outputLayout'] . "\n";
$formatter = new LineFormatter ( $outputLayout, $dateFormat );

$streamHandler = new StreamHandler ( $testPath . $logFileName, Logger::INFO );
$streamHandler->setFormatter ( $formatter );

global $logger;
$logger = new Logger ( 'ConvertToXML' );
$logger->pushHandler ( $streamHandler );

global $processedIdPs;

/**
 * FUNCTIONS *
 */

/**
 * Reads a JSON File and extract all the SURFconext IdP in production
 * return an array of these IdP
 */
function extractIdPFromJSON() {
	global $logger;
	global $processedIdPs;
	global $JSONFile;
	
	$logger->info ( "Extracting IdPs information from JSON source file..." );
	
	$JSONentities = file_get_contents ( $JSONFile );
	
	if ($JSONentities == false) {
		$logger->critical ( "File " . $JSONFile . " cannot be read... Aborting operations" );
		die ();
	} // if
	
	$connections = json_decode ( $JSONentities, true );
	
	$processedIdPs = count ( $connections );
	$logger->info ( "The JSON file contains " . $processedIdPs . " entries" );
	
	return $connections;
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
	
	$logger->info ( "Getting rid of useless IdPs informations..." );
	
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
	
	$logger->info ( "Replacing SSO endpoints with the stepup gateway one..." );
	
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
		$IdPsArray [$key] ['metadata'] ['SingleSignOnService'] [0] ['Location'] = $StepUpIdPSSOEndpoint . $IdPEntityIDHash;
	} // foreach
	
	$logger->info ( "Done. ". count ( $IdPsArray ) . " IdPs SSO endpoints were replaced." );
	
	return $IdPsArray;
} // replaceIdPsSSOendpoints

/**
 * INPUT an array containing SAML IdP informations and the name of the output file
 * OUTPUT a unique EntitiesDescriptor XML file
 * Uses TWIG templates engine with SURFconext IdP EntitiesDescriptor template
 */
function outputEntitiesDescriptor($IdPsArray, $outputFileName) {
	global $logger;
	global $testPath;
	$logger->info ( "Preparing 1 SAML EntitiesDescriptor file" );
	
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
	$template = $twig->loadTemplate ( $entitiesDescritorTemplate );
	// Populate the template
	$output = $template->render ( $IdPsArray );
	
	// Pretty format the outpout
	$doc = new DOMDocument ( '1.0', 'utf-8' );
	$doc->preserveWhiteSpace = false;
	$doc->formatOutput = true;
	$doc->loadXML ( $output );
	$resultCode = file_put_contents ( $testPath . 'output/' . $outputFileName, $doc->saveXML () );
	if ($resultCode== false) {
		$logger->critical ( "Cannot save output file: ". $testPath . $outputFileName. " Check writing rights. Aborting..." );
		die ( "Cannot create/write file. Aborting... \n" );
	}//if
} // outputEntitiesDescriptor

/**
 * ********************** MAIN **********************
 */
$processingTime = time();
$logger->info ( "Generating Metadata file..." );

$IdPArray = extractIdPFromJSON ();

$CleanIdPArray = cleanIdPInfos ( $IdPArray );
$JSONSuuasIdPMD = replaceIdPsSSOendpoints ( $CleanIdPArray, $StepUpIdPSSOEndpoint );

outputEntitiesDescriptor ( $JSONSuuasIdPMD, $outputFileName );

$processingTime = time() - $processingTime;
$logger->info ( "\nRunning time: ". $processingTime. " seconds");
$logger->info ( "************** END - Metadata generated *****************" );

?>