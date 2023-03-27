FROM owncloud
ENV HOST=oc1
RUN ln -s /tls/oc1.crt /tls/server.cert
RUN ln -s /tls/oc1.key /tls/server.key

RUN apt-get update -y && apt-get install -y php-ldap