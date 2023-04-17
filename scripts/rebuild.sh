#!/bin/bash
set -e

# base image for owncloud image:
cd servers/apache-php
touch Dockerfile # work around https://github.com/orgs/community/discussions/38878
docker build -t apache-php .

# base image for oc1, oc2 and oc3 images:
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

# image for oc3:
cd ../oc3
touch Dockerfile # work around https://github.com/orgs/community/discussions/38878
docker build -t oc3 .

# cd ../keycloak
# # touch Dockerfile
# docker-compose up -d --build
# # docker-compose -f ../keycloak/docker-compose.yml up -d --build


cd ../ldap
# touch Dockerfile
docker-compose up -d --build
# docker-compose -f ../keycloak/docker-compose.yml up -d --build
