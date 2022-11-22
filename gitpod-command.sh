#!/bin/bash
set -e
cd core
git pull
cd ..
./scripts/start-testnet.sh
