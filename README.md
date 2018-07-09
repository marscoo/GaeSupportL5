# GaeSupport
A fork of https://github.com/shpasser/GaeSupportL5, with a few notable differences.

### No CacheFS & Optimizer
In the original repo, CacheFS was used to reduce GCS usage and optimize the request runtime.
This, however, caused large amount of calls to memcache, and, more importantly, led to some serious bugs if you had 
several app engine versions / services with different configurations.

The removal of CacheFS does not impact the request runtime, since I was able to avoid all GCS calls with some minimal 
pre-deployment operations.

* Laravel only loads the services.json file from disk if a pre-compiled one isn't available, so we generate it upfront 
during deployment. (`artisan optimize`)
* Cached config and routes are loaded from the bootstrap directory anyway, so you don't really to cache them separately.
(`artisan config:cache`, `artisan route:cache`)
* The only limitation of removing CacheFS is with caching compiled views, which still have to go through the GCS
cycle. For my use case, I don't use views so this wasn't an issue.

### Additions    
Implemented `gae:prepare` which runs a sequence of commands to prepare the app for deployment, 
including optimizations, config and route caching, etc. The command also applies some post-processing
to the cached config so it works on GAE.

### Code style
The code was reformatted using PHPStorm's presets.