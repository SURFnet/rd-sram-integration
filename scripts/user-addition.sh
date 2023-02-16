#!/bin/bash
set -e
echo Creating regular group 'localfirst' on oc2
docker exec maria2.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_groups (gid) values ('localfirst');"
echo Adding local user to regular group on oc2
docker exec oc2.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "marie"}]}}]}' -H 'Content-Type: application/json' https://oc2.docker/index.php/apps/federatedgroups/scim/Groups/localfirst

echo Adding foreign user to regular group on oc1
docker exec oc1.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "marie#oc2.docker"}]}}]}' -H 'Content-Type: application/json' https://oc1.docker/index.php/apps/federatedgroups/scim/Groups/localfirst

echo Adding foreign user to regular group on oc2
docker exec oc2.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "einstein#oc1.docker"}]}}]}' -H 'Content-Type: application/json' https://oc2.docker/index.php/apps/federatedgroups/scim/Groups/localfirst
