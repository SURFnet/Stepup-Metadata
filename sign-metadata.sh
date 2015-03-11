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

#GET the SAML document ID
IdValue=$(grep -o ' ID="[^"]*"' $fileToSign | sed 's/[ID="]//g' | tr -d '[[:space:]]');

## Set JAVA_HOME
export JAVA_HOME=/usr

#The two commands below could be merged

echo "The current SAML document's ID number is: " $IdValue;
./xmlsectool/xmlsectool.sh --sign --inFile $fileToSign --outFile $signedFile --referenceIdAttributeName $IdValue --digest SHA-256 --signatureAlgorithm $signatureAlgorithm --key $key --certificate $certificate

echo "Checking signature and validating the schema compliance";
./xmlsectool/xmlsectool.sh --inFile $signedFile  --validateSchema --schemaDirectory schema --xsd --verifySignature --certificate $certificate
