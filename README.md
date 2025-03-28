# Whisp template project
Use this template project to get started with [Whisp](https://github.com/WhispPHP/whisp), the pure PHP SSH server for TUIs, in 1 minute flat

## What's included
This template sets you up with the [Whisp](https://github.com/WhispPHP/whisp) server, example TUI apps, and [Laravel Prompts](https://laravel.com/docs/12.x/prompts#main-content).

## Files

- `whisp-server.php` - Runs the SSH server on port 2020, `php whisp-server.php`
- `apps/` - Holds our apps
    - `howdy-world.php` - Ridiculously basic script to show how simple things can be
    - `prompts.php` - More complex Laravel Prompts setup to show what's supported
    - `confetti.php` - Draws confetti without Laravel Prompts
    - `mouse.php` - Basic mouse movement and click support

## Testing

Run the server, then SSH to each app:

**Run the server**
```bash
php whisp-server.php
```

**Run the howdy app**
```bash
ssh howdy@localhost -p2020
```

**Run the prompts app**
We don't need to pass the app or script name here as it's the default
```bash
ssh localhost -p2020
```
