#!/bin/sh

if [ ! -d "schema" ]; then
	echo "Creating schema directory"
	mkdir "schema"
	cd schema
	echo "Downloading the necessary schema files..."
	curl -O http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd -O http://www.w3.org/TR/xmlenc-core/xenc-schema.xsd -O https://svn.shibboleth.net/cpp-sp/branches/Rel_1_3/schemas/shibboleth-metadata-1.0.xsd -O http://docs.oasis-open.org/security/saml/v2.0/saml-schema-metadata-2.0.xsd -O http://docs.oasis-open.org/security/saml/v2.0/saml-schema-assertion-2.0.xsd
	cd ..
fi

## Set JAVA_HOME
export JAVA_HOME=/usr

#./xmlsectool.sh --help
VALUE=$(grep -oP ' ID="[^"]*"' suaas-transparent-metadata-unsigned.xml | sed 's/[ID="]//g');

#XMLSIG algorithms http://www.w3.org/TR/xmlsec-algorithms/

echo "The current SAML document's ID number is: " $VALUE;
./xmlsectool.sh --sign --inFile suaas-transparent-metadata-unsigned.xml --outFile suaas-transparent-metadata.xml --referenceIdAttributeName $VALUE --signatureAlgorithm http://www.w3.org/2001/04/xmldsig-more#rsa-sha256  --key signing.key --certificate signing.crt

echo "Checking signature and validating the schema compliance";
./xmlsectool.sh --inFile suaas-transparent-metadata.xml  --validateSchema --schemaDirectory schema --xsd --verifySignature --certificate signing.crt