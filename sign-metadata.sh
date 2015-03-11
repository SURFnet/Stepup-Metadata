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

# FIRST RUN - Download schemas and create symbolic link
if [ ! -d "schema" ]; then
	echo "Creating schema directory"
	mkdir "schema"
	cd schema
	echo "Downloading the necessary schema files..."
	curl -O http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd -O http://www.w3.org/TR/xmlenc-core/xenc-schema.xsd -O https://svn.shibboleth.net/cpp-sp/branches/Rel_1_3/schemas/shibboleth-metadata-1.0.xsd -O http://docs.oasis-open.org/security/saml/v2.0/saml-schema-metadata-2.0.xsd -O http://docs.oasis-open.org/security/saml/v2.0/saml-schema-assertion-2.0.xsd
	cd ..
fi

if [ ! -f xmlsectool ]; then
	ln -s $xmlSecToolDir xmlsectool
fi


## Set JAVA_HOME
export JAVA_HOME=/usr

#The two commands below could be merged

echo "The current SAML document's ID number is: " $IdValue;
./xmlsectool/xmlsectool.sh --sign --inFile $fileToSign --outFile $signedFile --referenceIdAttributeName $IdValue --digest SHA-256 --signatureAlgorithm $signatureAlgorithm --key $key --certificate $certificate

echo "Checking signature and validating the schema compliance";
./xmlsectool/xmlsectool.sh --inFile $signedFile  --validateSchema --schemaDirectory schema --xsd --verifySignature --certificate $certificate
