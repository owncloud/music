#!/bin/bash
#
# ownCloud music
#
# @author Morris Jobke
# @copyright 2015 Morris Jobke <hey@morrisjobke.de>
#

OWNCLOUD=$1
USER=$2
PASSWORD=$3

if [[ "$OWNCLOUD" == "stable8" ]];
then
    curl -X POST -u admin:admin -d "userid=$USER&password=$PASSWORD" http://localhost:8888/ocs/v1.php/cloud/users
    exit $?
fi

if [[ "$OWNCLOUD" == "stable7" ]];
then
    # patch AJAX call
    sed -i "s/OCP\\\\JSON::callCheck/#OCP\\\\JSON::callCheck/g" settings/ajax/createuser.php

    curl -X POST -u admin:admin -d "username=$USER&password=$PASSWORD" http://localhost:8888/index.php/settings/ajax/createuser.php

    # reset change
    sed -i "s/#OCP\\\\JSON::callCheck/OCP\\\\JSON::callCheck/g" settings/ajax/createuser.php

    exit 0
fi

OC_PASS=$PASSWORD ./occ user:add $USER --password-from-env
exit $?
