#!/bin/bash

# create_pki_materials.sh directory authority [signer_directory]
# Places certificates and new PKI keys in given directory
# if second argument is provided, the cert is signed by materials in that directory,
# otherwise cert is self-signed

# To initialize the CA dir:
# We keep the CA materials in /usr/share/geni-ch/.ca
#
# sudo mkdir /usr/share/geni-ch/.ca
# sudo cd /usr/share/geni-ch/.ca
# sudo touch geniCA/index.txt
# sudo echo "01" > geniCA/serial
#
#
# Then to create materials
# This first call creates both root and SA
# create_pki_materials.sh /usr/share/geni-ch/.pki SA
# This uses the root materials to sign the MA, etc.
# create_pki_materials.sh /usr/share/geni-ch/.pki MA /usr/share/geni-ch/.pki

# Parse arguments
TARGET=$1
AUTHORITY=$2
FQDN=`hostname -f`

# Self-signed cert if we don't have a signer (then this is the root)
if [ $# -eq 2 ]; then
    # Self-signed case
    SIGNER=$TARGET
    openssl req -x509 -nodes -days 365 -subj "/CN=$FQDN" -newkey rsa:1024 -keyout $TARGET/rootkey.pem -out $TARGET/rootcert.pem
else 
    # We'll use the materials from another directory
    SIGNER=$3;
fi

# Now we generate a cert request for this authority
openssl req -new -newkey rsa:1024 -nodes -subj "/CN=$FQDN.$AUTHORITY" -keyout $TARGET/mykey.$AUTHORITY.pem -out $TARGET/mycert-req.pem

# Now we sign the cert request with the keys of the signer (myself or as provided)
openssl ca -config openssl.cnf -notext -outdir $TARGET -out $TARGET/mycert.$AUTHORITY.pem -cert $SIGNER/rootcert.pem -keyfile $SIGNER/rootkey.pem -infiles $TARGET/mycert-req.pem

rm $TARGET/mycert-req.pem




