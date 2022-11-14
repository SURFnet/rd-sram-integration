#!/bin/bash
set -e

# base image for owncloud image:
cd servers/apache-php
touch Dockerfile # work around https://github.com/orgs/community/discussions/38878
docker build -t apache-php .

# base image for oc1 image and oc2 image:
cd ../owncloud
touch Dockerfile # work around https://github.com/orgs/community/discussions/38878
docker build -t owncloud .

# image for oc1:
cd ../oc1
touch Dockerfile # work around https://github.com/orgs/community/discussions/38878
docker build -t oc1 .

# image for oc2:
cd ../oc2
touch Dockerfile # work around https://github.com/orgs/community/discussions/38878
docker build -t oc2 .
