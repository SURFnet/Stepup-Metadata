# Stepup-Metadata

It is composed of two PHP applications that:

	getJSONConnections: Download SAML entities from SURFconext resources registry API and save production IdPs metadata into a unique pretty-printed JSON file;
    convertJSONToXML: Reads the SAML entities JSON file,extracts relevant informations for metadata generation;
    convertJSONToXML: Replaces all IdPs SSO endpoints with the Step Up IdP endpoint adding the hash value of the each IdP as a trailing string to that endpoint;
        e.g.  "Location="https://suuas.surfconext.nl/authentication/idp/single-sign-on/key:default/80e917885da2dd2624b1408b6b69fa2a (final step-up IdP base URL not fixed);
    convertJSONToXML: Outputs a unique unsigned SAML EntitiesDescriptor file.

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
        git clone https://github.com/SURFnet/Stepup-Metadata.git
        to /opt/Stepup-Metadata/src (change path/name if needed)
    Install TWIG and Monolog
        cd /opt/Stepup-Metadata/src (where there's the "composer.json")
        sudo composer install
    Check the repository Unix rights (for write log)

# Run the program

./run.sh

    The log file is on the log directory (check if problem)
