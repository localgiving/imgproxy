# Main localgiving image proxy app.
FROM ubuntu:14.04
MAINTAINER Kevin Sedgley <kevin@localgiving.com>

RUN apt-get update -y
RUN apt-get upgrade -y

# Standard LAMP stuff, minus the M
RUN apt-get -y install apache2
RUN apt-get -y install php5
RUN apt-get -y install libapache2-mod-php5
RUN apt-get -y install php5-gd
RUN apt-get -y install libapache2-mod-security2

# Specific Imagey Magicky type stuff
RUN apt-get -y install php5-imagick

# Grab phpThumb
ADD https://github.com/JamesHeinrich/phpThumb/archive/master.zip /opt/phpThumb.zip
RUN apt-get -y install unzip
RUN unzip /opt/phpThumb.zip -d /opt
RUN cp -Rf /opt/phpThumb-master/* /var/www/

# Get the main app
ADD public /var/www/html/

# Set permissions
RUN chmod 644 /var/www/html/.htaccess
RUN chown www-data /var/www/html/.htaccess

# Get SSL certificates and other resources if available
ADD resources /opt/resources
ADD conf /opt/conf

# Move the apache conf file round, enable rewriting
RUN sed -i "s/AllowOverride None/AllowOverride All/g" /etc/apache2/apache2.conf
RUN cp /opt/conf/default.conf /etc/apache2/sites-enabled/000-default.conf
RUN a2enmod rewrite

# Remove exposing PHP info
RUN sed -i "s/expose_php = On/expose_php = Off/g" /etc/php5/apache2/php.ini

# Get files for startup
ADD startup.sh /opt/startup.sh
RUN chmod +x /opt/startup.sh
ADD init.sh /opt/init.sh
RUN chmod +x /opt/init.sh

# Get AWS / S3 stuff
ADD https://github.com/aws/aws-sdk-php/releases/download/2.8.16/aws.phar /var/www/imgproxy/

# Remove in production
RUN apt-get -y install vim

EXPOSE 80
EXPOSE 443
