FROM owncloud
ENV HOST=oc2
RUN ln -s /tls/oc2.crt /tls/server.cert
RUN ln -s /tls/oc2.key /tls/server.key

RUN apt-get update -y && apt-get install -y php-ldap