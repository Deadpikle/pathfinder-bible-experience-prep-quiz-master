# Pathfinder Bible Quiz Engine

## Setup

Minimum requirements: PHP 7.1+ and a MariaDB database.

1. `curl -s https://getcomposer.org/installer | php`
2. `php composer.phar install --no-dev`
3. `vendor/bin/phinx init .`
4. `vendor/bin/phinx migrate -e development`
5. Edit phinx.yml with your db details (note that default_database is really the default environment -_- -- see https://github.com/cakephp/phinx/issues/984)
6. `vendor/bin/phinx migrate -e development` (or other environment name)
7. Seed database using `vendor/bin/phinx seed:run`
8. Copy `database.sample.php` to `database.php` and edit it with the db credentials (should match the ones in the phinx.yml file)
9. Copy the `config.sample.php` to `config.php`. You don't necessarily have to adjust anything here unless you want to change the session name (for instance, if you're hosting multiple versions of the site. If so, these should be unique between sites on the same host).

If you have issues with the database migrations, https://stackoverflow.com/a/25782795/3938401 might be helpful, and you might want to use 127.0.0.1 instead of `localhost`

You should now be able to login via `pbedb7`. I highly suggest changing that password.
