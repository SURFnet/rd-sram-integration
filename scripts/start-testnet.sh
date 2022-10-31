#!/bin/bash
set -e
echo "starting with start-from-ownCloud.sh"
echo "starting maria1.docker"
docker run -d --network=testnet -e MARIADB_ROOT_PASSWORD=eilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek --name=maria1.docker mariadb --transaction-isolation=READ-COMMITTED --binlog-format=ROW --innodb-file-per-table=1 --skip-innodb-read-only-compressed
echo "starting oc1.docker"
docker run -d --network=testnet --name=oc1.docker -v /root/rd-sram-integration/Surf:/var/www/html/lib/private/Share20/Surf oc1
echo "sleeping"
sleep 15
echo "executing init.sh on oc1.docker"
docker exec -e DBHOST=maria1.docker -e USER=einstein -e PASS=relativity  -u www-data oc1.docker sh /init.sh
# docker exec maria1.docker mariadb -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek owncloud
echo "done with start-from-ownCloud.sh"
