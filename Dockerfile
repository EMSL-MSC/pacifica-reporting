FROM php:5.6-apache

RUN apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get -y install \
       apt-utils \
       unzip \
       sqlite3 \
       php5-pgsql \
       php5-sqlite \
       vim

    #DEBIAN_FRONTEND=noninteractive apt-get -y upgrade

RUN a2enmod rewrite
ENV CI_ENV unit_testing
ENV CI_ROOTED true

EXPOSE 80

COPY websystem/system /var/www/html/system
COPY websystem/index.php /var/www/html/
COPY tests /var/www/html/tests
COPY apache_conf/modules /etc/apache2/conf-enabled/
COPY apache_conf/sites /etc/apache2/sites-available/
COPY config_files/general.ini /etc/myemsl/
RUN ln -s /var/www/html/application/resources /var/www/html/project_resources
RUN cp -f /usr/share/php5/php.ini-development /usr/local/etc/php/php.ini
RUN ln -s /usr/share/php5/pgsql/* /usr/local/etc/php/conf.d/
RUN ln -s /usr/lib/php5/20131226/p* /usr/local/lib/php/extensions/no-debug-non-zts-20131226/
RUN echo 'date.timezone = America/Los_Angeles' | tee "/usr/local/etc/php/conf.d/timezone.ini"
COPY resources /var/www/html/resources
COPY application /var/www/html/application
RUN cat tests/database/myemsl_metadata-eus.sql | sqlite3 tests/database/myemsl_metadata-eus.sqlite3
RUN cat tests/database/myemsl_metadata-myemsl.sql | sqlite3 tests/database/myemsl_metadata-myemsl.sqlite3
RUN cat tests/database/myemsl_metadata-website_prefs.sql | sqlite3 tests/database/myemsl_metadata-website_prefs.sqlite3

RUN chown -R "$APACHE_RUN_USER:$APACHE_RUN_GROUP" /var/www/html


CMD ["apache2-foreground"]
