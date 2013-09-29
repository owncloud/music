README
======

[![Build Status](https://secure.travis-ci.org/owncloud/music.png)](http://travis-ci.org/owncloud/music)

**This is under heavy development and isn't supposed to be deployed to a production server!**

I want to see it live - but how?
--------------------------------

Requirements:

 * appframework > 0.103
 * ownCloud 5 or 6

Known bugs:

 * ugly play icons in IE8 [#62](https://github.com/owncloud/music/issues/62)
 * Ampache not working yet [#62](https://github.com/owncloud/music/issues/62)
 * maybe slow for large music collections [#62](https://github.com/owncloud/music/issues/62)

Happy testing!
--------------

L10n hints
----------

You need to patch the extract regex to extract the strings, because this app
uses other delimiters (`[[` and `]]`) than a native AngularJS app (`{{` and `}}`).

File: `build/node_modules/grunt-angular-gettext/tasks/extract.js`

Sometimes translatable strings aren't detected. Try to move the `translate` attribute
more to the beginning of the HTML element.

For each translation the msgid has to be set (not set for plural translations by the
script).
