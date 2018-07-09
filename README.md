# GaeSupport
A fork of https://github.com/shpasser/GaeSupportL5, with a few notable differences:
- CacheFS related optimizations were removed:
    - CacheFS was used to ensure laravel does not read the services.json file from GCS in each request. This, however, isn't needed
    when using `artisan optimize`, as it generates a `services.php` file you can deploy with your application.
    - CacheFS was used for configuration caching, which is also deployed along with your application if you pre-cache it locally (`artisan config:cache`)
    - CacheFS was handy for compiled views, but I didn't need this optimization.
    
- Implemented `gae:prepare` which runs a sequence of commands to prepare the app for
deployment, including optimizations, config and route caching, etc. The command also applies some post-processing
to the cached config so it works on GAE.
- The code was reformatted using PHPStorm's presets.