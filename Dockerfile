FROM php:8.2-apache

# 1. PostgreSQL ड्राइवर और Apache Rewrite मॉड्यूल ऑन करना
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && a2enmod rewrite

# 2. Apache के रूट को public फ़ोल्डर पर सेट करना
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# 3. पूरे प्रोजेक्ट को सर्वर पर कॉपी करना
COPY . /var/www/html/

# 4. परमिशन सेट करना ताकि फाइल्स रीड हो सकें
RUN chown -R www-data:www-data /var/www/html
