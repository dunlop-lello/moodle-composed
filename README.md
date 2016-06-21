moodle-composed
===============

Sample project for installing moodle via composer.

Note that it is currently necessary to perform initialisation via composer update prior to adding moodle & plugins to the requires section; this is because composer doesn't currently have a mechanism to install composer plugins before resolving dependencies.

A possible work-around is to move moodle and dependencies to their own section instead of tradition requires; I'm not keen on this idea, but it may be a pragmatic solution.

Phil Lello
Dunlop-Lello Consulting LTD
https://consult.dunlop-lello.uk/
