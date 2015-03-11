#!/bin/sh

#EDIT the XMLSecTool version
XMLSecToolVersion="xmlsectool-1.2.0"

# INSTALLATION
# Download XMLSecTool
# Create a symbolic link
# Download XML schemas 

if [ ! -d $XMLSecToolVersion ]; then
	curl -O http://shibboleth.net/downloads/tools/xmlsectool/latest/$XMLSecToolVersion-bin.zip

	#You probably want to check the signature
	#curl -O http://shibboleth.net/downloads/tools/xmlsectool/latest/$XMLSecToolVersion-bin.zip.asc
	#gpg --verify $XMLSecToolVersion-bin.zip.asc
	#Will display the signing KeyID
	#gpg --keyserver pgpkeys.mit.edu --recv-ke KeyID
	#Run again
	#gpg --verify $XMLSecToolVersion-bin.zip.asc

	unzip $XMLSecToolVersion-bin.zip
	rm -f $XMLSecToolVersion-bin.zip
fi

if [ ! -h xmlsectool ]; then
	ln -s $XMLSecToolVersion xmlsectool
fi


if [ ! -d "schema" ]; then
	echo "Creating schema directory"
	mkdir "schema"
	cd schema
	echo "Downloading the necessary schema files..."
	curl -O http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd -O http://www.w3.org/TR/xmlenc-core/xenc-schema.xsd -O https://svn.shibboleth.net/cpp-sp/branches/Rel_1_3/schemas/shibboleth-metadata-1.0.xsd -O http://docs.oasis-open.org/security/saml/v2.0/saml-schema-metadata-2.0.xsd -O http://docs.oasis-open.org/security/saml/v2.0/saml-schema-assertion-2.0.xsd
	cd ..
fi