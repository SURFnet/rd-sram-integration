#!/bin/bash
set -e

if [ ! -d "./core" ];
then 
	echo -e "It's not there\n"
    # git clone --branch=surf-dev --depth=1 https://github.com/pondersource/core
    git clone --branch=feat/oidc-ldap --depth=1 https://github.com/pondersource/core
fi
./scripts/gencerts.sh
./scripts/rebuild.sh
docker pull mariadb
docker pull quay.io/keycloak/keycloak:12.0.4
docker pull osixia/openldap:1.5.0
docker pull osixia/openldap:latest
docker pull osixia/phpldapadmin:latest
docker pull jlesage/firefox
docker pull jlesage/firefox:v1.17.1
docker network create testnet