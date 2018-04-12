# Refined Storage website

This is the repository for the Refined Storage website.

## How to run
1) Clone the repo
2) `composer install`
3) Initialize a SQLite database based on `schema.sql` (call it `refinedstorage.sqlite`)
4) Copy `env.json.example` to `env.json`
5) Make sure at least PHP 7.1 is on your PATH and run `run-server.bat` (if you're on Linux, check out the command it uses and run it manually)

An instance is now running on localhost, port 80.