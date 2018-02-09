# Pathfinder Bible Quiz Engine

## Setup

1. `curl -s https://getcomposer.org/installer | php`
2. `php composer.phar install --no-dev`
3. `vendor/bin/phinx init .`
4. `vendor/bin/phinx migrate -e development`
5. Edit phinx.yml with your db details
6. `vendor/bin/phinx migrate -e development` (or other environment name)
7. Seed database using `vendor/bin/phinx seed:run`

If you have issues, https://stackoverflow.com/a/25782795/3938401 might be helpful, and you might want to use 127.0.0.1 instead of `localhost`
