# Stepup-Metadata

PHP application that consume a local JSON file (previously extracted from JANUS REST API). The application:

    Reads the SAML entities JSON file, extracts only "on production/active" SURFconext IdPs and their relevant informations for metadata generation;
    Replaces all IdPs SSO endpoints with the Step Up IdP endpoint adding the hash value of the each IdP as a trailing string to that endpoint;
        e.g.  "Location="https://suuas.surfconext.nl/authentication/idp/single-sign-on/key:default/80e917885da2dd2624b1408b6b69fa2a (final step-up IdP base URL not fixed);
    Outputs a SAML EntityDescriptor file for each IdP.

The code uses/needs:

    PHP 5;
    Composer for packages management;
    TWIG template engine (needs version 5.2.4 or greater);
    Monolog as log engine;

# Installation

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

cd /opt/Stepup-Metadata/src

php convertJSONToXML.php

    The output files will be in the output directory
    The log file is on the log directory (check if problem)
