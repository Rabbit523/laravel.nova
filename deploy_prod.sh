#!/bin/bash
COMMIT_SHA=$1
mv /var/www/backend_new /var/www/backend_${COMMIT_SHA}
cd /var/www/backend_${COMMIT_SHA}
export COMPOSER_ALLOW_SUPERUSER=1
composer install --optimize-autoloader --no-dev
composer dump-autoload --optimize --no-dev --classmap-authoritative
ln -s /var/www/.backend /var/www/backend_${COMMIT_SHA}/.env
php artisan migrate --force
php artisan route:cache
sudo chown -R www-data:www-data /var/www/backend_${COMMIT_SHA}/storage
ln -s -n -f -T /var/www/backend_${COMMIT_SHA} /var/www/backend
sudo mkdir -p /var/www/backend/storage/app/analytics/
sudo ln -s /var/www/service-account-credentials.json /var/www/backend/storage/app/analytics/service-account-credentials.json
ln -s /var/www/oauth-public.key /var/www/backend/storage/oauth-public.key
ln -s /var/www/oauth-private.key /var/www/backend/storage/oauth-private.key
ln -s /var/www/pass.p12 /var/www/backend/storage/pass.p12
cd /var/www/backend
php artisan nova:publish
ln -s /var/www/backend/nova/ /var/www/backend/vendor/laravel/nova
sudo service php7.2-fpm reload
sudo php artisan horizon:terminate
