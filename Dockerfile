FROM php:5.6-apache

RUN apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get -y install \
       apt-utils \
       unzip \
       php5-pgsql \
       vim && \

    DEBIAN_FRONTEND=noninteractive apt-get -y upgrade

RUN a2enmod rewrite

COPY application /var/www/html/application
COPY resources /var/www/html/resources
COPY websystem/system /var/www/html/system
COPY websystem/index.php /var/www/html/
COPY apache_conf/modules /etc/apache2/conf-enabled/
COPY apache_conf/sites /etc/apache2/sites-available/
COPY config_files/general.ini /etc/myemsl/

RUN chown -R "$APACHE_RUN_USER:$APACHE_RUN_GROUP" /var/www/html

ENV CI_ENV development
ENV CI_ROOTED true

EXPOSE 80
