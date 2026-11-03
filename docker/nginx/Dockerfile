# Nginx image for the KNMI site: serves the static files and passes PHP to the "php" service.
FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/php-fpm.conf /etc/nginx/snippets/php-fpm.conf
COPY . /var/www/html
RUN rm -rf /var/www/html/docker /var/www/html/docker-compose.yml /var/www/html/.github /var/www/html/.env*
