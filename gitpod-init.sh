#!/bin/bash
set -e

git clone --branch=accept-ocm-to-groups --depth=1 https://github.com/pondersource/core
./scripts/gencerts.sh
./scripts/rebuild.sh
docker pull mariadb
docker pull jlesage/firefox:v1.17.1
docker pull harrykodden/scim-server
docker network create testnet