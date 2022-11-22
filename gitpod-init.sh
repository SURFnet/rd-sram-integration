#!/bin/bash
set -e

git clone --branch=accept-ocm-to-groups --depth=1 http://github.com/pondersource/core
./scripts/gencerts.sh
./scripts/rebuild.sh
docker network create testnet