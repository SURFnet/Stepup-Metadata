#!/bin/sh

#EDIT below 
certificate="signing.crt"
key="signing.key"
fileToSign="output/suaas-transparent-metadata-unsigned.xml"
signedFile="output/suaas-transparent-metadata.xml"
xmlSecToolDir="xmlsectool-1.2.0"

#Reference http://www.w3.org/TR/xmlsec-algorithms/
signatureAlgorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"

## ####################################### ##
## YOU DON'T HAVE TO EDIT BELOW - NORMALLY ##

## Set JAVA_HOME
export JAVA_HOME=/usr

#The two commands below could be merged
echo "Signing the SAML metadata file " $fileToSign
./xmlsectool/xmlsectool.sh --sign --referenceIdAttributeName ID --inFile $fileToSign --outFile $signedFile --digest SHA-256 --signatureAlgorithm $signatureAlgorithm --key $key --certificate $certificate

if [ $? -ne 0 ]; then
	#DO SOMETHING
	exit $?
fi

# XMLSECTOOL: The signing and signature verification actions are mutually exclusive
echo "Checking signature and validating the schema compliance";
./xmlsectool/xmlsectool.sh --inFile $signedFile  --validateSchema --schemaDirectory schema --xsd --verifySignature --certificate $certificate

if [ $? -ne 0 ]; then
	#DO SOMETHING
	exit $?
fi