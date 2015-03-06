# Stepup-Metadata

It is composed of two PHP applications
	getJSONConnections
	convertJSONToXML
These two application could be run separately.

## Functionning:

### getJSONConnections
* Download SAML entities from SURFconext resources registry API and save production IdPs metadata into a unique pretty-printed JSON file;

### convertJSONToXML
* Reads the SAML entities JSON file,extracts relevant informations for metadata generation;
* Replaces all IdPs SSO endpoints with the Step Up IdP endpoint adding the hash value of the each IdP as a trailing string to that endpoint;
   e.g.  "Location="https://suuas.surfconext.nl/authentication/idp/single-sign-on/key:default/80e917885da2dd2624b1408b6b69fa2a (final step-up IdP base URL not fixed);
* Outputs a unique unsigned SAML EntitiesDescriptor file.

The code uses/needs:

	CURL
    PHP 5;
    Composer for packages management;
    TWIG template engine (needs version 5.2.4 or greater);
    Monolog as log engine;

# Installation

Install CURL
Install PHP5
Install Composer

	curl -sS https://getcomposer.org/installer | php
	mv composer.phar /usr/bin/composer
   
Install the "JSON to XML SAML Metadata Converter" application

	cd /opt/ (change as will)
	git clone https://github.com/SURFnet/Stepup-Metadata.git

Install TWIG and Monolog

	cd /opt/Stepup-Metadata/src (where there's the "composer.json")
	sudo composer install
    
Check the repository Unix rights (for write log)

# Run the programs

	./run.sh (runs both programs -- Pay attention to write privileges)

The log file is on the log directory (check if problem)
