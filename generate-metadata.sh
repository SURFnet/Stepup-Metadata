#!/bin/sh

# Run Metadata converter
php src/getJSONconnections.php
php src/convertJSONToXML.php