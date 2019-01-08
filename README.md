# Refined Storage website

This is the repository for the Refined Storage website.

## How to run
1) Clone the repo
2) `composer install`
3) Initialize a SQLite database based on `schema.sql` (call it `refinedstorage.sqlite` and store it in the project root)
4) Copy `env.json.example` to `env.json` in the project root
5) Make sure at least PHP 7.1 is on your PATH and run `run-server.bat` (if you're on Linux, check out the command it uses and run it manually)

An instance is now running on localhost, port 80.

## Running on Apache
1) Edit your apache config file (as below) and copy the .htaccess file is to the public folder.
```
<VirtualHost *:80>
        <Directory /var/www/html/public>
                Options Indexes FollowSymLinks
                AllowOverride all
                Require all granted
        </Directory>
        
        ServerAdmin webmaster@localhost
        DocumentRoot /var/www/html/public
        RewriteEngine on
        
        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>

```
2) use the commands below
``` 
cd /var/www/html
chown www-data:www-data * -R
chmod u+x refinedstorage.sqlite
```
3) restart your apache2 service 
`service apache2 restart` 
your website is running public on port 80.
