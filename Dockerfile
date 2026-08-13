FROM php:8.2-apache

# PostgreSQL से कनेक्ट करने के लिए जरूरी ड्राइवर इंस्टॉल करना
RUN apt-get update && apt-get install -y libpq-dev && docker-php-ext-install pdo pdo_pgsql

# आपके public फ़ोल्डर को मुख्य रूट बनाना
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# पूरा प्रोजेक्ट सर्वर पर कॉपी करना
COPY . /var/www/html/
