# Stepup-Metadata

Two independant PHP applications:

1. getJSONConnections
2. convertJSONToXML

They can be run separately as long as the input JSON file OR the resource registry API are available for each program.

## Functionning:

### getJSONConnections
* Download SAML entities from SURFconext resources registry API 
* Select and save production IdPs metadata into a unique pretty-printed JSON file;

### convertJSONToXML
* Reads the SAML entities JSON file,extracts relevant informations for metadata generation;
* Replaces all IdPs SSO endpoints with the Step Up IdP endpoint adding the hash value of each IdP as a trailing string to that endpoint;  
  * e.g.  "Location="https://suuas.surfconext.nl/authentication/idp/single-sign-on/80e917885da2dd2624b1408b6b69fa2a (final step-up IdP base URL not fixed);
* Outputs an unsigned pretty-printed SAML EntitiesDescriptor file.

**Remark** 
* *The programs outputs unsigned medatada file in compliance with SAML2 Metadata schema (http://docs.oasis-open.org/security/saml/v2.0/saml-schema-metadata-2.0.xsd) but that does not guarantee proper work of SAML2 softwares. Thus, the program relies on the presence of mandatory informations provided by the resource registry API.*

* The program is delivered with XMLSecTool install and launch scripts (install-xmlsectool.sh and sign-metadata.sh)
  * XMLSecTool can be installed and used embedded in the Step-up Metadata Generator program

The programs use/need:

	CURL
    PHP 5;
    Composer for packages management;
    TWIG template engine (needs version 5.2.4 or greater);
    Monolog as log engine;

# Installation

* Install CURL
* Install PHP5
* Install Composer  

	curl -sS https://getcomposer.org/installer | php
	mv composer.phar /usr/bin/composer
   
Install the "Step-up Metadata generator" application

	cd /opt/ (change at will)
	git clone https://github.com/SURFnet/Stepup-Metadata.git

Install TWIG and Monolog

	cd /opt/Stepup-Metadata/src (where there's the "composer.json")
	sudo composer install
    
Check the repository Unix rights (log writing)

# Run the programs

	./generate-metadata.sh (runs both programs -- Pay attention to write privileges)

The log file is on the log directory (check if problem)
