#!/bin/bash
set -e

function waitForPort {
  x=$(docker exec -it $1 ss -tulpn | grep $2 | wc -l)
  until [ $x -ne 0 ]
  do
    echo Waiting for $1 to open port $2, this usually takes about 10 seconds ... $x
    sleep 1
    x=$(docker exec -it $1 ss -tulpn | grep $2 | wc -l)
  done
  echo $1 port $2 is open
}

export REPO_DIR=`pwd`
echo Repo dir is $REPO_DIR


# keycloak start
docker run -d --name=keycloak.docker \
    -p 8080:8080 \
    -e DB_VENDOR=h2 \
    -e PROXY_ADDRESS_FORWARDING="true" \
    -e KEYCLOAK_USER=admin \
    -e KEYCLOAK_PASSWORD=password \
    -e KEYCLOAK_STATISTICS=all \
    -e KEYCLOAK_LOGLEVEL=INFO \
    --network=testnet \
    quay.io/keycloak/keycloak:12.0.4
# keycloak end

# ldap start
# docker run --name=ldap.docker \
#     -p 389:389 -p 636:636 \
#     --network=testnet \
#     --detach \
#     osixia/openldap:1.5.0
# ldap end


echo "starting maria1.docker"
docker run -d --network=testnet -e MARIADB_ROOT_PASSWORD=eilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek --name=maria1.docker mariadb --transaction-isolation=READ-COMMITTED --binlog-format=ROW --innodb-file-per-table=1 --skip-innodb-read-only-compressed
echo "starting oc1.docker"
docker run -d --network=testnet --name=oc1.docker \
  -v $REPO_DIR:/var/www/html/apps/rd-sram-integration \
  -v $REPO_DIR/core/apps/files_sharing:/var/www/html/apps/files_sharing \
  -v $REPO_DIR/core/apps/user_ldap:/var/www/html/apps/user_ldap \
  oc1

echo "starting maria2.docker"
docker run -d --network=testnet -e MARIADB_ROOT_PASSWORD=eilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek --name=maria2.docker mariadb --transaction-isolation=READ-COMMITTED --binlog-format=ROW --innodb-file-per-table=1 --skip-innodb-read-only-compressed
echo "starting oc2.docker"
docker run -d --network=testnet --name=oc2.docker \
  -v $REPO_DIR:/var/www/html/apps/rd-sram-integration \
  -v $REPO_DIR/core/apps/files_sharing:/var/www/html/apps/files_sharing \
  -v $REPO_DIR/core/apps/user_ldap:/var/www/html/apps/user_ldap \
  oc2

echo "starting maria3.docker"
docker run -d --network=testnet -e MARIADB_ROOT_PASSWORD=eilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek --name=maria3.docker mariadb --transaction-isolation=READ-COMMITTED --binlog-format=ROW --innodb-file-per-table=1 --skip-innodb-read-only-compressed
echo "starting oc3.docker"
docker run -d --network=testnet --name=oc3.docker \
  -v $REPO_DIR:/var/www/html/apps/rd-sram-integration \
  -v $REPO_DIR/core/apps/files_sharing:/var/www/html/apps/files_sharing \
  -v $REPO_DIR/core/apps/user_ldap:/var/www/html/apps/user_ldap \
  oc3

echo "starting firefox tester"
docker run -d --name=firefox -p 5800:5800 -v /tmp/shm:/config:rw --network=testnet --shm-size 2g jlesage/firefox:v1.17.1

waitForPort maria1.docker 3306
waitForPort oc1.docker 443
echo "executing init.sh on oc1.docker"
docker exec -e DBHOST=maria1.docker -e USER=einstein -e PASS=relativity  -u www-data oc1.docker sh /init.sh
# docker exec maria1.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud

waitForPort maria2.docker 3306
waitForPort oc2.docker 443
echo "executing init.sh on oc2.docker"
docker exec -e DBHOST=maria2.docker -e USER=marie -e PASS=radioactivity -u www-data oc2.docker sh /init.sh
# docker exec maria2.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud

waitForPort maria3.docker 3306
waitForPort oc3.docker 443
echo "executing init.sh on oc3.docker"
docker exec -e DBHOST=maria3.docker -e USER=adorno -e PASS=identity -u www-data oc3.docker sh /init.sh
# docker exec maria3.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud

echo Creating regular group 'federalists' on oc1
docker exec maria1.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_groups (gid) values ('federalists');"
echo Adding local user to regular group on oc1
docker exec oc1.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "einstein"}]}}]}' -H 'Content-Type: application/json' https://oc1.docker/index.php/apps/federatedgroups/scim/Groups/federalists
echo Adding foreign user to regular group on oc1
docker exec oc1.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "marie#oc2.docker"}]}}]}' -H 'Content-Type: application/json' https://oc1.docker/index.php/apps/federatedgroups/scim/Groups/federalists
docker exec oc1.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "adorno#oc3.docker"}]}}]}' -H 'Content-Type: application/json' https://oc1.docker/index.php/apps/federatedgroups/scim/Groups/federalists

echo Creating regular group 'federalists' on oc2
docker exec maria2.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_groups (gid) values ('federalists');"
echo Adding foreign user to regular group on oc2
docker exec oc2.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "einstein#oc1.docker"}]}}]}' -H 'Content-Type: application/json' https://oc2.docker/index.php/apps/federatedgroups/scim/Groups/federalists
docker exec oc2.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "adorno#oc3.docker"}]}}]}' -H 'Content-Type: application/json' https://oc2.docker/index.php/apps/federatedgroups/scim/Groups/federalists
echo Adding local user to regular group on oc2
docker exec oc2.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "marie"}]}}]}' -H 'Content-Type: application/json' https://oc2.docker/index.php/apps/federatedgroups/scim/Groups/federalists

echo Creating regular group 'federalists' on oc3
docker exec maria3.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_groups (gid) values ('federalists');"
echo Adding foreign user to regular group on oc2
docker exec oc3.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "einstein#oc1.docker"}]}}]}' -H 'Content-Type: application/json' https://oc3.docker/index.php/apps/federatedgroups/scim/Groups/federalists
docker exec oc3.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "marie#oc2.docker"}]}}]}' -H 'Content-Type: application/json' https://oc3.docker/index.php/apps/federatedgroups/scim/Groups/federalists
echo Adding local user to regular group on oc2
docker exec oc3.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "adorno"}]}}]}' -H 'Content-Type: application/json' https://oc3.docker/index.php/apps/federatedgroups/scim/Groups/federalists


echo Creating regular group 'helpdesk' on oc2
docker exec maria2.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_groups (gid) values ('helpdesk');"
echo Adding local user to regular group on oc2
docker exec oc1.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "marie"}]}}]}' -H 'Content-Type: application/json' https://oc2.docker/index.php/apps/federatedgroups/scim/Groups/helpdesk


echo Creating custom group 'custard with mustard' on oc1
docker exec maria1.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_custom_group (group_id, uri, display_name) values (1, 'Custard with Mustard', 'Custard with Mustard');"
echo Adding local user to custom group on oc1
docker exec oc1.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "einstein"}]}}]}' -H 'Content-Type: application/json' https://oc1.docker/index.php/apps/federatedgroups/scim/Groups/Custard%20with%20Mustard
echo Adding foreign user to custom group on oc1
docker exec oc1.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "marie#oc2.docker"}]}}]}' -H 'Content-Type: application/json' https://oc1.docker/index.php/apps/federatedgroups/scim/Groups/Custard%20with%20Mustard


echo Creating custom group 'custard with mustard' on oc2
docker exec maria2.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_custom_group (group_id, uri, display_name) values (1, 'Custard with Mustard', 'Custard with Mustard');"
echo Adding foreign user to custom group on oc2
docker exec oc2.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "einstein#oc1.docker"}]}}]}' -H 'Content-Type: application/json' https://oc2.docker/index.php/apps/federatedgroups/scim/Groups/Custard%20with%20Mustard
echo Adding local user to custom group on oc2
docker exec oc2.docker curl -X PATCH -d'{"Operations":[{"op": "add","path": "members","value": {"members": [{"value": "marie"}]}}]}' -H 'Content-Type: application/json' https://oc2.docker/index.php/apps/federatedgroups/scim/Groups/Custard%20with%20Mustard



echo Now browse to http://\<host\>:5800 to see a Firefox instance that sits inside the Docker testnet.
