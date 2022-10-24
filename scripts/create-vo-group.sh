#!/bin/bash
set -e
echo Creating group on oc1
docker exec maria1.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_groups (gid) values ('federalists');"
echo Adding local user to group on oc1
docker exec maria1.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_group_user (gid, uid) values ('federalists', 'einstein');"
echo Adding foreign user to group on oc1
docker exec maria1.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_group_user (gid, uid) values ('federalists', 'marie#oc2.docker');"

echo Creating group on oc2
docker exec maria2.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_groups (gid) values ('federalists');"
echo Adding foreign user to group on oc2
docker exec maria2.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_group_user (gid, uid) values ('federalists', 'einstein#oc1.docker');"
echo Adding local user to group on oc2
docker exec maria2.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud -e "insert into oc_group_user (gid, uid) values ('federalists', 'marie');"
