README
======

[![Build Status](https://secure.travis-ci.org/owncloud/music.png)](http://travis-ci.org/owncloud/music)

**This is undy heavy development and isn't supposed to be deployed to a production server!**

I want to see it live - but how?
--------------------------------

As this branch doesn't do any metadata scanning yet, you will need to import some dummy data. I use [these sql insert statements](https://gist.github.com/kabum/f6d0ac1a8d1e6e6162f5) to add this. Keep in mind to change the `user_id` (`mjob`) to your used one in the ownCloud instance. This should be your login name.

Currently there is no ownCloud implementation. Therefore we use the [shiva client prototype](https://github.com/tooxie/shiva-client).

    $ git clone git@github.com:tooxie/shiva-client.git

Adjust `API_URL` in `shiva-client/shiva/server.py` to

    https://user:password@server/path/to/index.php/apps/music/api

Start the shiva client server (sounds weird, but that prototype proxies the traffic) with

    $ cd shiva-client/shiva
    $ python2 server.py

Go to `localhost:9001` and browse the dummy data.
