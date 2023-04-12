echo Installing ownCloud
php console.php maintenance:install --admin-user $USER --admin-pass $PASS --database "mysql" --database-name "owncloud" --database-user "root" --database-pass "eilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek" --database-host "$DBHOST"
echo Installing Custom Groups
php console.php app:enable customgroups
echo Installing Federated Groups
php console.php app:enable federatedgroups
echo Installing user_ldap
php console.php app:enable user_ldap
echo Installing openidconnect
php console.php app:enable openidconnect
echo Editing Config


# sed -i "3 i\  'allow_local_remote_servers' => true," config/config.php
sed -i "8 i\      1 => 'oc1.docker'," /var/www/html/config/config.php
sed -i "9 i\      2 => 'oc2.docker'," /var/www/html/config/config.php
sed -i "3 i\  'sharing.managerFactory' => 'OCA\\\\FederatedGroups\\\\ShareProviderFactory'," /var/www/html/config/config.php
sed -i "4 i\  'sharing.remoteShareesSearch' => 'OCA\\\\FederatedGroups\\\\ShareeSearchPlugin'," /var/www/html/config/config.php

# insert memcache to first line needed by oidc
sed -i "3 i\  'memcache.local' => '\\\\OC\\\\Memcache\\\\APCu'," /var/www/html/config/config.php
# insert http.cookie.samesite to first line needed by oidc
sed -i "3 i\  'http.cookie.samesite' => 'None'," /var/www/html/config/config.php

# insert OIDC config START
# please don't insert any othe line in between (order matters) 
sed -i "3 i\  'openid-connect' => [" /var/www/html/config/config.php
sed -i "4 i\    'auto-provision'    => ['enabled' => false]," /var/www/html/config/config.php
sed -i "5 i\    'provider-url'      => 'http://keycloak.docker:8080/auth/realms/master/'," /var/www/html/config/config.php
sed -i "6 i\    'client-id'         => 'oc_oidc_client'," /var/www/html/config/config.php
sed -i "7 i\    'client-secret'     => '643342df-206f-4be8-b775-3a65eabb3f74'," /var/www/html/config/config.php
sed -i "8 i\    'loginButtonName'   => 'OIDC Login'," /var/www/html/config/config.php
sed -i "9 i\  ]," /var/www/html/config/config.php
# insert OIDC config END


# Now you can start adding to line 3 again from here