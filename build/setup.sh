#!/bin/bash
#
# ownCloud music
#
# @author Morris Jobke
# @copyright 2015 Morris Jobke <hey@morrisjobke.de>
#

OWNCLOUD=$1
DB=$2
DB_NAME=$3
DB_USER=$4
DB_PASS=$5
ADMIN_USER=$6
ADMIN_PASS=$7

if [[ "$OWNCLOUD" == 'master' ]];
then
    ./occ maintenance:install --admin-user $ADMIN_USER --admin-pass $ADMIN_PASS --database $DB --database-name $DB_NAME --database-user $DB_USER --database-pass $DB_PASS
    exit $?
fi

cat > ./config/autoconfig.php <<DELIM
<?php
\$AUTOCONFIG = array (
  'installed' => false,
  'dbtype' => '$DB',
  'dbtableprefix' => 'oc_',
  'adminlogin' => '$ADMIN_USER',
  'adminpass' => '$ADMIN_PASS',
  'dbuser' => '$DB_USER',
  'dbname' => '$DB_NAME',
  'dbhost' => 'localhost',
  'dbpass' => '$DB_PASS',
  'directory' => \\OC::\$SERVERROOT . '/data',
);
DELIM

if [[ "$DB_NAME" == 'sqlite' ]];
then
    cat > ./config/autoconfig.php <<DELIM
    <?php
    \$AUTOCONFIG = array (
      'installed' => false,
      'dbtype' => 'sqlite',
      'dbtableprefix' => 'oc_',
      'adminlogin' => '$ADMIN_USER',
      'adminpass' => '$ADMIN_PASS',
      'directory' => \\OC::\$SERVERROOT . '/data',
    );
DELIM
fi

# trigger installation
php -f index.php | grep -i -C9999 error && echo "Error during setup" && exit 101

exit 0
