cd /var/www/backend/
ln -s /var/www/.backend .env
ln -s /var/www/oauth-public.key /var/www/backend/storage/oauth-public.key
ln -s /var/www/oauth-private.key /var/www/backend/storage/oauth-private.key
ln -s /var/www/pass.p12 /var/www/backend/storage/pass.p12
sudo chown -R www-data:ubuntu storage
php artisan migrate --force
sudo service php7.2-fpm reload
sudo php artisan horizon:terminate
