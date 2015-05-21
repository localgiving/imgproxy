#!/usr/bin/env bash
# See if SSL provided
if [ -n "$ssl_crt_location" ]
then
     a2enmod ssl
     cp /opt/conf/default-ssl.conf /etc/apache2/sites-available/default-ssl.conf
     cp /opt/resources/chain.crt /etc/apache2/chain.crt
     cp /opt/resources/ssl.crt /etc/apache2/ssl.crt
     cp /opt/resources/ssl.key /etc/ssl/private/private.key
     a2ensite default-ssl
fi

# See if referer list provided
if [ -n "$referers" ]
then
    # replaces in htaccess to send 403 if not a matching referer
    sed -i "s:#--HOTLINK--#:RewriteCond %{HTTP_REFERER} !^$\nRewriteCond %{HTTP_REFERER} !^$referers [NC]\nRewriteRule .? - [NC,F,L]:g" /var/www/html/.htaccess
    echo "Added referer rule"
fi

# Start the startup
/opt/startup.sh

/bin/bash
