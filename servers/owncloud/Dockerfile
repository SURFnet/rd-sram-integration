FROM apache-php
RUN rm -rf /var/www/html
USER www-data
RUN git clone -b surf-dev --depth=1 https://github.com/pondersource/core.git --recursive --shallow-submodules
WORKDIR /var/www/core
USER root
RUN apt update && apt install -y composer curl dirmngr apt-transport-https lsb-release ca-certificates iproute2 curl unzip php-curl
RUN curl -sS https://getcomposer.org/installer -o /root/composer-setup.php
RUN php /root/composer-setup.php --install-dir=/usr/local/bin --filename=composer
RUN composer -V
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash -
RUN apt install nodejs
RUN npm install -g yarn
USER www-data
WORKDIR /var/www
RUN mv core html
WORKDIR /var/www/html
RUN composer install --no-dev
RUN make install-nodejs-deps
ENV PHP_MEMORY_LIMIT="512M"
RUN cd apps && git clone https://github.com/owncloud/customgroups
RUN cd apps/customgroups && composer install --no-dev
RUN cd apps/customgroups && yarn install
RUN cd apps/customgroups && yarn build
RUN cd apps && git clone https://github.com/SURFnet/rd-sram-integration
RUN cd apps && ln -s rd-sram-integration/federatedgroups
ADD init.sh /init.sh
RUN mkdir -p data ; touch data/owncloud.log
USER root
CMD /usr/sbin/apache2ctl -DFOREGROUND & tail -f /var/log/apache2/error.log & tail -f data/owncloud.log