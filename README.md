# Minify for ocStore 2.3

Combine, compress css, js files and html formatting into one line.

## Description

Collects all css and js files, combines them into one and removes extra spaces, hyphens without breaking the code, and can also compress them with gzip.
He knows how to format html by removing extra spaces, hyphenation, simultaneously compressing js and css without breaking the code.

## Setting

Upload the contents of the upload folder to the root of the site and install the module in the admin panel. If the module does not appear in the list, then you need to give permission to view, edit and check if it is not marked for hiding in the list. Select the required parameters in the module settings and save.

### Gzip

For gzip compression to work, you need to write the following code in .htaccess

``
AddEncoding gzip .jgz
#add support gzip JavaScript
RewriteCond% {HTTP_USER_AGENT} ". * Safari. *" [OR]
RewriteCond% {HTTP: Accept-Encoding} gzip
RewriteCond% {REQUEST_FILENAME} .jgz -f
RewriteRule (. *) \. Js $ $ 1 \ .js.jgz [L]
AddType "text / javascript" .js.jgz
#add support gzip CSS
RewriteCond% {HTTP_USER_AGENT} ". * Safari. *" [OR]
RewriteCond% {HTTP: Accept-Encoding} gzip
RewriteCond% {REQUEST_FILENAME} .jgz -f
RewriteRule (. *) \. Js $ $ 1 \ .css.jgz [L]
AddType "text / css" .css.jgz
AddEncoding gzip .jgz
``

## Attention!!!

* The module overwrites the `system / framework.php` file and it should be editable!
* When changing CSS and JS, you must clear the cache in the module settings for the module to regenerate the files! 

