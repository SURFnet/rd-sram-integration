#!/bin/bash
set -e
echo "starting with start-to-ownCloud.sh"
echo "starting maria2.docker"
docker run -d --network=testnet -e MARIADB_ROOT_PASSWORD=eilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek --name=maria2.docker mariadb --transaction-isolation=READ-COMMITTED --binlog-format=ROW --innodb-file-per-table=1 --skip-innodb-read-only-compressed
echo "starting oc2.docker"
docker run -d --network=testnet --name=oc2.docker -v /root/rd-sram-integration/Surf:/var/www/html/lib/private/Share20/Surf oc2
echo "sleeping"
sleep 15
echo "executing init.sh on oc2.docker"
docker exec -e DBHOST=maria2.docker -e USER=marie -e PASS=radioactivity -u www-data oc2.docker sh /init.sh
# docker exec maria2.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud
echo "done with start-to-ownCloud.sh"
