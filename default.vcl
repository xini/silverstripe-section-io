# See the VCL chapters in the Users Guide at https://www.varnish-cache.org/docs/
# and http://varnish-cache.org/trac/wiki/VCLExamples for more examples.

# Marker to tell the VCL compiler that this VCL has been adapted to the
# new 4.0 format.
vcl 4.0;

backend default {
	.host = "next-hop";
	.port = "80";
}

# The following VMODs are available for use if required:
#import geoip; # see https://github.com/varnish/libvmod-geoip
#import header; # see https://github.com/varnish/libvmod-header

sub vcl_recv {
	# Happens before we check if we have this in cache already.
	#
	# Typically you clean up the request here, removing cookies you don't need,
	# rewriting the request, etc.
	
	# https redirect
	# also uncomment vcl_synth at the bottom!
#	if (req.http.X-Forwarded-Proto !~ "(?i)https") {
#		return (synth(750, ""));
#	}
	
	# clean up accept-encoding
	if (req.http.Accept-Encoding) {
		if (req.http.Accept-Encoding ~ "gzip") {
			set req.http.Accept-Encoding = "gzip";
		} else if (req.http.Accept-Encoding ~ "deflate") {
			set req.http.Accept-Encoding = "deflate";
		} else {
			unset req.http.Accept-Encoding;
		}
	}	
	
	# remove cookies for static content based on /assets/.htaccess
	if (req.url ~ ".*\.(?:js|css|bmp|png|gif|jpg|jpeg|ico|pcx|tif|tiff|au|mid|midi|mpa|mp3|ogg|m4a|ra|wma|wav|cda|avi|mpg|mpeg|asf|wmv|m4v|mov|mkv|mp4|ogv|webm|swf|flv|ram|rm|doc|docx|txt|rtf|xls|xlsx|pages|ppt|pptx|pps|csv|cab|arj|tar|zip|zipx|sit|sitx|gz|tgz|bz2|ace|arc|pkg|dmg|hqx|jar|pdf|woff|woff2|eot|ttf|otf|svg)(?=\?|&|$)") {
		unset req.http.Cookie;
		return (hash);
	}
	
	# pass for admin and forms
	if (
		
		# Admin and dev URLs
		(req.url ~ "^/admin|Security|dev/") ||

		# Staging/Previewing URLs while in /admin
		(req.url ~ "stage=") ||
		
		# ss multistep forms
		(req.url ~ "MultiFormSessionID=") ||
		
		# check for login cookie
		(req.http.Cookie ~ "sslogin=")

	) {
		return (pass);
	}	
	
	# remove common cookies
	if (req.http.Cookie) {
	
		# remove silverstripe cookies
		set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(cms-panel-collapsed-cms-menu)=[^;]*", "");
		set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(cms-menu-sticky)=[^;]*", "");
		
		# Remove any Google Analytics based cookies 
		# (removes everything starting with an underscore, which also includes AddThis, DoubleClick and others)
		set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_[_a-zA-Z0-9\-]+)=[^;]*", "");
		set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(utm[a-z]+)=[^;]*", "");
		
		# Remove the Avanser phone tracking cookies
		set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(AUA[0-9]+)=[^;]*", "");
		
		# Remove the StatCounter cookies
		set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(sc_is_visitor_unique)=[^;]*", "");

		# Remove a ";" prefix, if present.
		set req.http.Cookie = regsub(req.http.Cookie, "^;\s*", "");

		# remove empty cookie
		if (req.http.Cookie == "") {
			unset req.http.cookie;
		}
	}
	
	# remove adwords gclid parameter
	set req.url = regsuball(req.url,"\?gclid=[^&]+$",""); # strips when QS = "?gclid=AAA"
	set req.url = regsuball(req.url,"\?gclid=[^&]+&","?"); # strips when QS = "?gclid=AAA&foo=bar"
	set req.url = regsuball(req.url,"&gclid=[^&]+",""); # strips when QS = "?foo=bar&gclid=AAA" or QS = "?foo=bar&gclid=AAA&bar=baz"

	# Strip hash, server doesn't need it.
	if (req.url ~ "\#") {
		set req.url = regsub(req.url, "\#.*$", "");
	}
	
	# Strip a trailing ? if it exists
	if (req.url ~ "\?$") {
		set req.url = regsub(req.url, "\?$", "");
	}
}

sub vcl_backend_fetch {
	# Called before sending the backend request.
	#
	# Typically you alter the request for the backend here. Overriding to the
	# required hostname, upstream Proto matching, etc
	
}

sub vcl_backend_response {
	# Happens after we have read the response headers from the backend.
	#
	# Here you clean the response headers, removing silly Set-Cookie headers
	# and other mistakes your backend does.
	
	# cache static content
	# set cache control header for css & js
	if (bereq.url ~ ".*\.(?:css|js)(?=\?|&|$)") { 
		unset beresp.http.set-cookie;
		set beresp.ttl = 604800s; # The number of seconds to cache inside Varnish: 1 week
		set beresp.http.Cache-Control = "public, max-age=604800"; # The number of seconds to cache in browser: 1 week
	}
	# set cache control header for images, audio, video
	if (bereq.url ~ ".*\.(?:bmp|png|gif|jpg|jpeg|ico|pcx|tif|tiff|au|mid|midi|mpa|mp3|ogg|m4a|ra|wma|wav|cda|avi|mpg|mpeg|asf|wmv|m4v|mov|mkv|mp4|ogv|webm|swf|flv|ram|rm)(?=\?|&|$)") {
		unset beresp.http.set-cookie;
		set beresp.ttl = 2592000s; # The number of seconds to cache inside Varnish: 1 month
		set beresp.http.Cache-Control = "public, max-age=2592000"; # The number of seconds to cache in browser: 1 month
	}
	# set cache control header for docs and archives
	if (bereq.url ~ ".*\.(?:doc|docx|txt|rtf|xls|xlsx|pages|ppt|pptx|pps|csv|cab|arj|tar|zip|zipx|sit|sitx|gz|tgz|bz2|ace|arc|pkg|dmg|hqx|jar|pdf)(?=\?|&|$)") {
		unset beresp.http.set-cookie;
		set beresp.ttl = 2592000s; # The number of seconds to cache inside Varnish: 1 month
		set beresp.http.Cache-Control = "public, max-age=2592000"; # The number of seconds to cache in browser: 1 month
	}
	# set cache control header for fonts
	if (bereq.url ~ ".*\.(?:woff|woff2|eot|ttf|otf|svg)(?=\?|&|$)") {
		unset beresp.http.set-cookie;
		set beresp.ttl = 2592000s; # The number of seconds to cache inside Varnish: 1 month
		set beresp.http.Cache-Control = "public, max-age=2592000"; # The number of seconds to cache in browser: 1 month
	}
	
	# normal pages 
    if (beresp.http.Content-Type ~ "^text/html"){
		if (
			(bereq.url ~ "^/(Security|admin|dev)") ||
			(bereq.url ~ "stage=") ||
			(bereq.url ~ "MultiFormSessionID=") ||
			(bereq.http.Cookie ~ "sslogin=") ||
			(beresp.http.X-SS-Form) ||
			(beresp.http.Pragma ~ "(?i)no-cache") 
		) {
			# set admin and form pages to uncacheable
			set beresp.uncacheable = true;
			set beresp.ttl = 120s;
		} else {
			# if just normal page, set cache control headers
			set beresp.ttl = 3600s;
			set beresp.http.Cache-Control = "public, max-age=600";
			# remove cookies
			unset beresp.http.set-cookie;
		}
	}
	
	# make sure svg files are compressed
	if (beresp.http.content-type ~ "image/svg\+xml") {
        set beresp.do_gzip = true;
    }
	
	# set grace period
	set beresp.grace = 6h;
	
	### DO NOT CHANGE ###
	# store url in cached object to use in ban()
	set beresp.http.x-url = bereq.url;
	
	# un-comment this to see in the X-Cookie-Debug header what cookies varnish still sees after cookie stripping in vcl_recv
	#set beresp.http.X-Cookie-Debug = "Request cookie: " + bereq.http.Cookie;
	
}

sub vcl_deliver {
	# Happens when we have all the pieces we need, and are about to send the
	# response to the client.
	#
	# You can do accounting or modifying the final object here.
	
	# remove session cookie for non-form pages (when someone visits a form page all subsequent pages woulnd't be cached otherwise)
	if (
		(resp.http.Content-Type ~ "^text/html") &&
		(req.http.Cookie) &&
		!(req.url ~ "^/(Security|admin|dev)") &&
		!(req.url ~ "stage=") &&
		!(req.url ~ "MultiFormSessionID=") &&
		!(req.method == "POST") &&
		!(req.http.Cookie ~ "sslogin=") &&
		!(resp.http.X-SS-Form)
	) {
		set resp.http.set-cookie = "PHPSESSID=deleted; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; HttpOnly";
	}
	
	# add cache response header
	if (obj.hits > 0) {
		set resp.http.X-Cache = "HIT";
	} else {
		set resp.http.X-Cache = "MISS";
	}
	
	# Remove some headers that give too much information about environment
	unset resp.http.X-Powered-By;
	unset resp.http.Server;
	unset resp.http.X-Varnish;
	unset resp.http.Via;
	
	### DO NOT CHANGE ###
	# remove saved url and protocol from object before delivery
	unset resp.http.x-url;
	unset resp.http.X-Forwarded-Proto;
	
}

sub vcl_hit {
	# deliver if ttl > 0, normal hit
	if (obj.ttl >= 0s) {
		return (deliver);
	}
	# deliver if ttl = 0 but grace still on
	if (obj.ttl + obj.grace > 0s) {
		return (deliver);
	}
	# fetch new content
	return (fetch);
}

# include protocol in cache key to prevent endless redirects
sub vcl_hash {
	if (req.http.X-Forwarded-Proto) {
		hash_data(req.http.X-Forwarded-Proto);
	}
}

# domain and https redirects
#sub vcl_synth {
#    if (resp.status == 750) {
#        set resp.status = 301;
#        set resp.http.Location = "https://" + req.http.host + req.url;
#        return(deliver);
#    }
#}
