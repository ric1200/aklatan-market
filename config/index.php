<?php
/**
 * Directory guard.
 * Apache is already told to refuse this folder through .htaccess, but if a host
 * ignores that file this page still stops directory listings and casual
 * browsing. Nothing here is meant to be reached through a web browser.
 */
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
exit('Not found.');
