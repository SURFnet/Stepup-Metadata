#!/bin/sh
set -e

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