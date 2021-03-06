#!/bin/sh
set -e

# Run Metadata generator - Run the SAML only if the download is successful
exitStatus=$(php src/getJSONconnections.php)

if [ "${exitStatus:-0}" == 0 ]; then 
	php src/convertJSONToXML.php
else 
	echo $exitStatus
	exit 1
fi