#!/bin/bash
set -e
export TLS_DIR=./servers/apache-php/tls

function createCert {
  echo Generating key and CSR for $1.docker
  openssl req -new -nodes \
    -out $TLS_DIR/$1.csr \
    -keyout $TLS_DIR/$1.key \
    -subj "/C=RO/ST=Bucharest/L=Bucharest/O=IT/CN=$1.docker"
  echo Creating extfile
  echo "subjectAltName = @alt_names" > $TLS_DIR/$1.cnf
  echo "[alt_names]" >> $TLS_DIR/$1.cnf
  echo "DNS.1 = $1.docker" >> $TLS_DIR/$1.cnf

  echo Signing CSR for $1.docker, creating cert.
  openssl x509 -req -days 365 -in $TLS_DIR/$1.csr \
    -CA $TLS_DIR/ocm-ca.crt -CAkey $TLS_DIR/ocm-ca.key -CAcreateserial \
    -out $TLS_DIR/$1.crt -extfile $TLS_DIR/$1.cnf
}

echo Creating $TLS_DIR
mkdir -p $TLS_DIR
echo Generating CA key
openssl genrsa -out $TLS_DIR/ocm-ca.key 2058
echo Generate CA self-signed certificate
openssl req -new -x509 -days 365 \
    -key $TLS_DIR/ocm-ca.key \
    -out $TLS_DIR/ocm-ca.crt \
    -subj "/C=RO/ST=Bucharest/L=Bucharest/O=IT/CN=ocm-ca"

createCert oc1
createCert oc2
