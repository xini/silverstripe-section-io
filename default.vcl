# See the VCL chapters in the Users Guide at https://www.varnish-cache.org/docs/
# and http://varnish-cache.org/trac/wiki/VCLExamples for more examples.

# Marker to tell the VCL compiler that this VCL has been adapted to the
# new 4.0 format.
vcl 4.0;

backend default {
	.host = "next-hop";
	.port = "80";
}

sub vcl_recv {
	# Happens before we check if we have this in cache already.
	#
	# Typically you clean up the request here, removing cookies you don't need,
	# rewriting the request, etc.
	
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
	
	# remove cookies for static content
	if (req.url ~ ".*\.(?:css|js|jpe?g|png|gif|ico|swf|flv|mp4|mov|f4v|mp3|aac|m4a|woff|eot|ttf|otf|svg)(?=\?|&|$)") {
		unset req.http.Cookie;
		return (hash);
	}
	
	# ss admin
	if (req.url ~ "^/(Security|admin|dev)" || req.url ~ "stage=") {
		return (pass);
	}
	
	# ss multistep form
	if( req.url ~ "MultiFormSessionID=" ) {
		return (pass);
	}
	
	# check for login cookie
	if ( req.http.Cookie ~ "sslogin=" ) {
		return (pass);
	}
	
	# remove tracking cookies
	if (req.http.Cookie) {
		set req.http.Cookie = regsuball(req.http.Cookie, "(^|(?<=; )) *__utm.=[^;]+;? *", "\1"); # standard ga cookies
		set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_dc_gtm_[A-Z0-9\-]+)=[^;]*", ""); # gtm cookies
		set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(_ga)=[^;]*", ""); # gtm ga cookies
		set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(AUA[0-9]+)=[^;]*", ""); # avanser cookies

		if (req.http.Cookie == "") {
			unset req.http.Cookie;
		}
	}
	
	# remove adwords gclid parameter
	set req.url = regsuball(req.url,"\?gclid=[^&]+$",""); # strips when QS = "?gclid=AAA"
	set req.url = regsuball(req.url,"\?gclid=[^&]+&","?"); # strips when QS = "?gclid=AAA&foo=bar"
	set req.url = regsuball(req.url,"&gclid=[^&]+",""); # strips when QS = "?foo=bar&gclid=AAA" or QS = "?foo=bar&gclid=AAA&bar=baz"
	
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
	if (bereq.url ~ ".*\.(?:jpe?g|png|gif|ico|swf|flv|mp4|mov|f4v|mp3|aac|m4a)(?=\?|&|$)") {
		set beresp.ttl = 2592000s; # The number of seconds to cache inside Varnish: 1 month
		set beresp.http.Cache-Control = "public, max-age=2592000"; # The number of seconds to cache in browser: 1 month
	}
	if (bereq.url ~ ".*\.(?:css|js)(?=\?|&|$)") {
		set beresp.ttl = 604800s; # The number of seconds to cache inside Varnish: 1 week
		set beresp.http.Cache-Control = "public, max-age=604800"; # The number of seconds to cache in browser: 1 week
	}
	if (bereq.url ~ ".*\.(?:woff|eot|ttf|otf|svg)(?=\?|&|$)") {
		set beresp.ttl = 2592000s; # The number of seconds to cache inside Varnish: 1 month
		set beresp.http.Cache-Control = "public, max-age=2592000"; # The number of seconds to cache in browser: 1 month
	}
	
	# set cache control header for pages
    if (beresp.http.Content-Type ~ "^text/html" && !(bereq.url ~ "^/(Security|admin|dev)") && !(bereq.http.Cookie ~ "sslogin=") && !(beresp.http.Pragma ~ "no-cache") ) {
		 set beresp.ttl = 3600s;
		 set beresp.http.Cache-Control = "public, max-age=600";
    }
	
	# set grace period
	set beresp.grace = 1d;
}

sub vcl_deliver {
	# Happens when we have all the pieces we need, and are about to send the
	# response to the client.
	#
	# You can do accounting or modifying the final object here.
	
	# add cache response header
	if (obj.hits > 0) {
		set resp.http.X-Cache = "HIT";
	} else {
		set resp.http.X-Cache = "MISS";
	}
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