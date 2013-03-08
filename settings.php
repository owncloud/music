<?php

OCP\User::checkLoggedIn();

$tmpl = new OCP\Template( 'media', 'settings');

return $tmpl->fetchPage();
