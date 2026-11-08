FROM php:8.3-apache
ENV SERVER_ID="ShopNow"
COPY servidor/ /var/www/html/
EXPOSE 80
